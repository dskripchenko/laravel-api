<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Exceptions\Handler;
use Illuminate\Http\Request;

it('delegates to ApiErrorHandler for api-endpoint route', function () {
    $handler = app(\Illuminate\Contracts\Debug\ExceptionHandler::class);
    expect($handler)->toBeInstanceOf(Handler::class);

    // Create a request that matches the api-endpoint route
    $request = Request::create('/api/v1/item/list', 'GET');
    $request->setRouteResolver(function () {
        $route = new \Illuminate\Routing\Route('GET', 'api/{version}/{controller}/{action}', fn() => null);
        $route->name('api-endpoint');
        $route->bind(app('request'));
        return $route;
    });

    $e = new \RuntimeException('test error');
    $response = $handler->render($request, $e);
    $data = json_decode($response->getContent(), true);
    expect($data['success'])->toBeFalse();
    expect($data['payload']['message'])->toBe('test error');
});

it('delegates to parent for non-api routes', function () {
    $handler = app(\Illuminate\Contracts\Debug\ExceptionHandler::class);

    $request = Request::create('/web/page', 'GET');
    $e = new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Not found');

    $response = $handler->render($request, $e);
    expect($response->getStatusCode())->toBe(404);
});

it('handles Throwable exceptions', function () {
    $handler = app(\Illuminate\Contracts\Debug\ExceptionHandler::class);

    $request = Request::create('/api/v1/item/list', 'GET');
    $request->setRouteResolver(function () {
        $route = new \Illuminate\Routing\Route('GET', 'api/{version}/{controller}/{action}', fn() => null);
        $route->name('api-endpoint');
        $route->bind(app('request'));
        return $route;
    });

    $e = new \DivisionByZeroError('division by zero');
    $response = $handler->render($request, $e);
    $data = json_decode($response->getContent(), true);
    expect($data['success'])->toBeFalse();
});

it('answers with the envelope for errors on named action routes', function () {
    // Laravel middleware on an action (auth, throttle) throws before the
    // action runs; the answer must still be the API's envelope.
    $handler = app(\Illuminate\Contracts\Debug\ExceptionHandler::class);

    $route = app('router')->getRoutes()->getByName('api.v1.open.ping');
    $request = Request::create('/api/v1/open/ping', 'GET');
    $request->setRouteResolver(fn () => $route->bind($request));

    $response = $handler->render($request, new \Illuminate\Auth\AuthenticationException('Unauthenticated.'));

    expect($response->getStatusCode())->toBe(401)
        ->and(json_decode($response->getContent(), true))->toBe([
            'success' => false,
            'payload' => ['errorKey' => 'unauthenticated', 'message' => 'Unauthenticated.'],
        ]);
});

it('answers with the status Laravel gives the exceptions no handler knows', function (\Throwable $e, int $status) {
    $handler = app(\Illuminate\Contracts\Debug\ExceptionHandler::class);

    $route = app('router')->getRoutes()->getByName('api.v1.open.ping');
    $request = Request::create('/api/v1/open/ping', 'POST');
    $request->setRouteResolver(fn () => $route->bind($request));

    $response = $handler->render($request, $e);

    expect($response->getStatusCode())->toBe($status)
        ->and(json_decode($response->getContent(), true))->toMatchArray([
            'success' => false,
            'payload' => ['errorKey' => 'http_error', 'message' => $e instanceof \Illuminate\Session\TokenMismatchException ? 'CSRF token mismatch.' : 'No query results for model [Tests\\Fixtures\\Missing].'],
        ]);
})->with([
    'CSRF mismatch' => [fn () => new \Illuminate\Session\TokenMismatchException('CSRF token mismatch.'), 419],
    'missing model' => [fn () => (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel('Tests\\Fixtures\\Missing'), 404],
]);
