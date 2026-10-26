<?php

namespace Dskripchenko\LaravelApi\Providers;

use Dskripchenko\LaravelApi\Console\Commands\ApiCache as ApiCacheCommand;
use Dskripchenko\LaravelApi\Console\Commands\ApiClear;
use Dskripchenko\LaravelApi\Console\Commands\ApiDocAssets;
use Dskripchenko\LaravelApi\Console\Commands\ApiDocClear;
use Dskripchenko\LaravelApi\Console\Commands\ApiExport;
use Dskripchenko\LaravelApi\Console\Commands\ApiGenerateTypes;
use Dskripchenko\LaravelApi\Console\Commands\ApiInstall;
use Dskripchenko\LaravelApi\Console\Commands\ApiLint;
use Dskripchenko\LaravelApi\Controllers\ApiDocumentationController;
use Dskripchenko\LaravelApi\Exceptions\ApiErrorHandler;
use Dskripchenko\LaravelApi\Exceptions\Handler;
use Dskripchenko\LaravelApi\Facades\ApiModule;
use Dskripchenko\LaravelApi\Components\BaseModule;
use Dskripchenko\LaravelApi\Requests\BaseApiRequest;
use Dskripchenko\LaravelApi\Transports\Mcp\McpController;
use Exception;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Class ApiServiceProvider
 * @package Dskripchenko\LaravelApi\Providers
 */
class ApiServiceProvider extends ServiceProvider
{

    public function boot(): void
    {
        $this->publishes([
            dirname(__DIR__, 2) . '/config/laravel-api.php' => config_path('laravel-api.php'),
        ], 'laravel-api-config');

        $this->loadViewsFrom(dirname(__DIR__, 2) . '/resources/views', 'api_module');

        $this->makeApiRoutes();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__, 2) . '/config/laravel-api.php',
            'laravel-api'
        );

        $this->app->bind('api_module', function () {
            return $this->getApiModule();
        });

        $this->app->bind('api_request', function () {
            return $this->getApiRequest();
        });

        // A singleton rather than a bind: other providers append exception
        // handlers to it (`ApiErrorHandler::addErrorHandler(...)` in their
        // boot). With a bind every resolution returned a new object — with only
        // the constructor's defaults — and everything appended vanished
        // silently.
        $this->app->singleton('api_error_handler', function () {
            return $this->getApiErrorHandler();
        });

        $this->app->singleton(
            ExceptionHandler::class,
            Handler::class
        );

        $this->commands(
            [
                ApiInstall::class,
                ApiGenerateTypes::class,
                ApiExport::class,
                ApiDocClear::class,
                ApiDocAssets::class,
                ApiLint::class,
                ApiCacheCommand::class,
                ApiClear::class,
            ]
        );

        // Cached OpenAPI specs (ApiDocumentationController) live in storage and
        // are only rebuilt when missing or in debug mode; deployments with a
        // persistent storage volume would keep serving stale docs. Hook the
        // cleanup into `optimize:clear`.
        if (method_exists($this, 'optimizes')) {
            $this->optimizes(clear: 'api:doc-clear', key: 'laravel-api-doc');

            // Only the clearing: `api:cache` freezes getMethods(), and an API
            // whose map changes at runtime must not get that from `optimize`
            // without asking for it.
            $this->optimizes(clear: 'api:clear', key: 'laravel-api');
        }

        parent::register();
    }

    /**
     * @return BaseModule
     */
    protected function getApiModule()
    {
        return new BaseModule();
    }

    /**
     * @return ApiErrorHandler
     */
    protected function getApiErrorHandler()
    {
        return new ApiErrorHandler();
    }

    /**
     * @return BaseApiRequest
     * @throws Exception
     */
    protected function getApiRequest()
    {
        return BaseApiRequest::getInstance();
    }

    /**
     * The MCP endpoint, when `laravel-api.mcp.enabled` is on — inside the API
     * prefix, ahead of the catch-all route.
     */
    public static function registerMcpRoutes(): void
    {
        if (!config('laravel-api.mcp.enabled', false)) {
            return;
        }

        $path = trim((string) config('laravel-api.mcp.path', 'mcp'), '/');

        Route::match(['get', 'post', 'delete'], "{$path}/{version}", static function (string $version) {
            return app()->call(McpController::class . '@handle', ['version' => $version]);
        })->name('api-mcp')
            ->middleware((array) config('laravel-api.mcp.middleware', []));
    }

    public function makeApiRoutes()
    {
        $middlewareGroupName = "api-middleware-group";
        /** @var Router $router */
        $router = app(Router::class);
        $router->middlewareGroup($middlewareGroupName, ApiModule::getApiMiddleware());

        // With `route:cache` the routes come from the cache file, and building
        // them again would be thrown away. The group above is not part of that
        // file: the cached routes refer to it by name.
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::group([
            'prefix' => ApiModule::getApiPrefix(),
        ], static function () use ($middlewareGroupName) {
            Route::get('doc', static function () {
                return app()->call(ApiDocumentationController::class . '@index');
            })->name('api-doc')
                ->middleware(ApiModule::getDocMiddleware());

            Route::get('doc/{version}', static function (string $version) {
                return app()->call(ApiDocumentationController::class . '@source', ['version' => $version]);
            })->name('api-doc-source')
                ->middleware(ApiModule::getDocMiddleware());

            static::registerMcpRoutes();

            $routeDefinitions = ApiModule::getRouteDefinitions();
            foreach ($routeDefinitions as $definition) {
                $perActionMiddleware = (array) ($definition['middleware'] ?? []);
                Route::match($definition['methods'], $definition['uri'], function () {
                    return ApiModule::makeApi();
                })->name($definition['name'])
                    ->middleware(array_merge([$middlewareGroupName], $perActionMiddleware))
                    // Through withoutMiddleware — it removes what came from
                    // the group too, not only the version's own list.
                    ->withoutMiddleware((array) ($definition['exclude-middleware'] ?? []));
            }

            Route::match(ApiModule::getAvailableApiMethods(), ApiModule::getApiUriPattern(), function () {
                return ApiModule::makeApi();
            })->name('api-endpoint')
                ->middleware($middlewareGroupName);

        });
    }
}
