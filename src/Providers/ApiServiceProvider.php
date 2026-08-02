<?php

namespace Dskripchenko\LaravelApi\Providers;

use Dskripchenko\LaravelApi\Console\Commands\ApiDocClear;
use Dskripchenko\LaravelApi\Console\Commands\ApiExport;
use Dskripchenko\LaravelApi\Console\Commands\ApiGenerateTypes;
use Dskripchenko\LaravelApi\Console\Commands\ApiInstall;
use Dskripchenko\LaravelApi\Controllers\ApiDocumentationController;
use Dskripchenko\LaravelApi\Exceptions\ApiErrorHandler;
use Dskripchenko\LaravelApi\Exceptions\Handler;
use Dskripchenko\LaravelApi\Facades\ApiModule;
use Dskripchenko\LaravelApi\Components\BaseModule;
use Dskripchenko\LaravelApi\Requests\BaseApiRequest;
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

        // Singleton, а не bind: обработчики исключений в него дописывают
        // другие провайдеры (`ApiErrorHandler::addErrorHandler(...)` в их
        // boot). С bind каждое разрешение возвращало новый объект — только с
        // дефолтами из конструктора, а всё дописанное молча пропадало.
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
            ]
        );

        // Cached OpenAPI specs (ApiDocumentationController) live in storage and
        // are only rebuilt when missing or in debug mode; deployments with a
        // persistent storage volume would keep serving stale docs. Hook the
        // cleanup into `optimize:clear`.
        if (method_exists($this, 'optimizes')) {
            $this->optimizes(clear: 'api:doc-clear', key: 'laravel-api-doc');
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

    public function makeApiRoutes()
    {
        $middlewareGroupName = "api-middleware-group";
        /** @var Router $router */
        $router = app(Router::class);
        $router->middlewareGroup($middlewareGroupName, ApiModule::getApiMiddleware());

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

            $routeDefinitions = ApiModule::getRouteDefinitions();
            foreach ($routeDefinitions as $definition) {
                $perActionMiddleware = (array) ($definition['middleware'] ?? []);
                Route::match($definition['methods'], $definition['uri'], function () {
                    return ApiModule::makeApi();
                })->name($definition['name'])
                    ->middleware(array_merge([$middlewareGroupName], $perActionMiddleware))
                    // Через withoutMiddleware — он снимает и то, что пришло из
                    // группы, а не только из собственного списка версии.
                    ->withoutMiddleware((array) ($definition['exclude-middleware'] ?? []));
            }

            Route::match(ApiModule::getAvailableApiMethods(), ApiModule::getApiUriPattern(), function () {
                return ApiModule::makeApi();
            })->name('api-endpoint')
                ->middleware($middlewareGroupName);

        });
    }
}
