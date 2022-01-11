<?php

namespace Dskripchenko\LaravelApi\Providers;

use Dskripchenko\LaravelApi\Console\Commands\ApiInstall;
use Dskripchenko\LaravelApi\Controllers\ApiDocumentationController;
use Dskripchenko\LaravelApi\Exceptions\ApiErrorHandler;
use Dskripchenko\LaravelApi\Exceptions\Handler;
use Dskripchenko\LaravelApi\Facades\ApiModule;
use Dskripchenko\LaravelApi\Components\BaseModule;
use Dskripchenko\LaravelApi\Requests\BaseApiRequest;
use Exception;
use Illuminate\Contracts\Debug\ExceptionHandler;
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
            dirname(__DIR__, 2) . '/resources/swagger-themes' => public_path('swagger-themes'),
        ]);

        $this->loadViewsFrom(dirname(__DIR__, 2) . '/resources/views', 'api_module');

        $this->makeApiRoutes();
    }

    public function register(): void
    {
        $this->app->bind('api_module', function () {
            return $this->getApiModule();
        });

        $this->app->bind('api_request', function () {
            return $this->getApiRequest();
        });

        $this->app->bind('api_error_handler', function () {
            return $this->getApiErrorHandler();
        });

        $this->app->singleton(
            ExceptionHandler::class,
            Handler::class
        );

        $this->commands(
            [
                ApiInstall::class,
            ]
        );

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
        Route::group([
            'prefix' => ApiModule::getApiPrefix(),
        ], function () {
            Route::get('doc', function () {
                return app()->call(ApiDocumentationController::class . '@index');
            })->name('api-doc');

            Route::match(ApiModule::getAvailableApiMethods(), ApiModule::getApiUriPattern(), function () {
                return ApiModule::makeApi();
            })->middleware(ApiModule::getApiMiddleware())->name('api-endpoint');
        });
    }
}
