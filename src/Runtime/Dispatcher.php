<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Runtime;

use Dskripchenko\LaravelApi\Components\BaseModule;
use Dskripchenko\LaravelApi\Facades\ApiErrorHandler;
use Dskripchenko\LaravelApi\Facades\ApiRequest;
use Dskripchenko\LaravelApi\Requests\BaseApiRequest;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Facade;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Calls an action without an HTTP request of its own.
 *
 * The call goes the way an HTTP request would: a request is made for the
 * action's route and sent through the router — so the middleware cascade, the
 * route parameters, `FormRequest` validation, `auth()` and `ApiRequest` all work
 * as they do over HTTP, and the answer is what an HTTP client would get. What it
 * skips is the HTTP kernel: the global middleware has already run for the
 * request this call runs in.
 *
 * That request is put back afterwards — the container's `request`, the
 * facades, `ApiRequest`, the router's current route — so the code that made
 * the call carries on in its own request. Its headers and cookies are passed on
 * to the call, which is how it is authenticated as the same caller; an
 * invocation's own headers come on top.
 */
final class Dispatcher
{
    /** Headers describing the body of the request the call runs in, not the call's. */
    private const NOT_PASSED_ON = ['HTTP_CONTENT_TYPE', 'HTTP_CONTENT_LENGTH', 'CONTENT_TYPE', 'CONTENT_LENGTH'];

    public function __construct(private readonly Application $app)
    {
    }

    public function dispatch(ApiInvocation $invocation): ApiResult
    {
        /** @var BaseModule $module */
        $module = $this->app->make('api_module');

        $api = $module->getApi($invocation->version);
        if (!$api) {
            return ApiResult::fromResponse(
                ApiErrorHandler::handle(new NotFoundHttpException('The requested version is not active!'))
            );
        }

        $httpMethod = $invocation->httpMethod
            ?? ($api::operations($invocation->version)->find($invocation->controllerKey, $invocation->actionKey)?->httpMethods[0] ?? 'post');

        // Taken before the call's request exists: making it replaces the
        // current ApiRequest already.
        $router = $this->app->make(Router::class);
        $state = $this->captureState($router);

        try {
            $request = $this->makeRequest($module, $invocation, strtoupper((string) $httpMethod), $state['request']);
            $this->enter($request);
            $response = $router->dispatch($request);
        } catch (\Throwable $e) {
            $response = ApiErrorHandler::handle($e);
        } finally {
            $this->restore($router, $state);
        }

        return ApiResult::fromResponse($response);
    }

    private function makeRequest(BaseModule $module, ApiInvocation $invocation, string $httpMethod, ?Request $outer): BaseApiRequest
    {
        $uri = '/' . trim($module->getApiPrefix(), '/') . '/' . str_replace(
            ['{version}', '{controller}', '{action}'],
            [$invocation->version, $invocation->controllerKey, $invocation->actionKey],
            $module->getApiUriPattern()
        );

        $server = [];
        $cookies = [];
        if ($outer !== null) {
            foreach ($outer->server->all() as $key => $value) {
                if (str_starts_with((string) $key, 'HTTP_') && !in_array($key, self::NOT_PASSED_ON, true)) {
                    $server[$key] = $value;
                }
            }
            $cookies = $outer->cookies->all();
        }

        foreach ($invocation->headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', (string) $name))] = $value;
        }

        // The input travels as a client would send it: in the query string for
        // GET, where every value arrives as a string, and as a JSON body
        // otherwise, where the types are kept — the way a JSON client calls.
        if ($httpMethod === 'GET') {
            $query = http_build_query($invocation->params);
            $symfonyRequest = Request::create($uri . ($query === '' ? '' : '?' . $query), $httpMethod, [], $cookies, [], $server);
        } else {
            $server['CONTENT_TYPE'] = 'application/json';
            $server['HTTP_ACCEPT'] ??= 'application/json';
            $symfonyRequest = Request::create(
                $uri,
                $httpMethod,
                [],
                $cookies,
                [],
                $server,
                (string) json_encode((object) $invocation->params, JSON_THROW_ON_ERROR)
            );
        }

        // createFromBase() makes it the current ApiRequest as well; restore()
        // puts back the one captured before.
        return BaseApiRequest::createFromBase($symfonyRequest);
    }

    /** @return array<string, mixed> */
    private function captureState(Router $router): array
    {
        return [
            'request' => $this->app->bound('request') ? $this->app->make('request') : null,
            'route' => $this->app->bound(Route::class) ? $this->app->make(Route::class) : null,
            'apiRequest' => BaseApiRequest::swapInstance(null),
            'router' => (fn (): array => [$this->current, $this->currentRequest])->call($router),
        ];
    }

    private function enter(BaseApiRequest $request): void
    {
        $this->app->instance('request', $request);
        $this->forgetResolved();
    }

    /** @param array<string, mixed> $state */
    private function restore(Router $router, array $state): void
    {
        BaseApiRequest::swapInstance($state['apiRequest']);

        if ($state['request'] !== null) {
            $this->app->instance('request', $state['request']);
        }

        if ($state['route'] !== null) {
            $this->app->instance(Route::class, $state['route']);
        }

        [$current, $currentRequest] = $state['router'];
        (function () use ($current, $currentRequest): void {
            $this->current = $current;
            $this->currentRequest = $currentRequest;
        })->call($router);

        $this->forgetResolved();
    }

    /** The facades keep what they resolved: the request of the call, or of the one before it. */
    private function forgetResolved(): void
    {
        Facade::clearResolvedInstance('request');
        ApiRequest::clearResolvedInstance('api_request');
    }
}
