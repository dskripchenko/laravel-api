<?php


namespace Dskripchenko\LaravelApi;


use Dskripchenko\LaravelApi\Components\ApiErrorHandler;
use Dskripchenko\LaravelApi\Components\BaseApiRequest;
use Dskripchenko\LaravelApi\Facades\ApiModule;
use Dskripchenko\LaravelApi\Components\ApiDocumentationController;
use Dskripchenko\LaravelApi\Components\BaseModule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ApiServiceProvider extends ServiceProvider
{

    public function boot()
    {
        $this->publishes(
            [
                dirname(__DIR__) . '/resources/swagger-themes' => public_path('swagger-themes'),
            ]
        );

        $this->loadViewsFrom(dirname(__DIR__) . '/resources/views', 'api_module');

        $this->makeApiRoutes();
    }

    public function register()
    {
        $this->app->bind(
            'api_module',
            function () {
                return $this->getApiModule();
            }
        );

        $this->app->bind(
            'api_request',
            function () {
                return $this->getApiRequest();
            }
        );

        $this->app->bind(
            'api_error_handler',
            function () {
                return $this->getApiErrorHandler();
            }
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
     * @throws \Exception
     */
    protected function getApiRequest()
    {
        return BaseApiRequest::getInstance();
    }

    public function makeApiRoutes()
    {
        Route::group(
            [
                'namespace' => ApiModule::getControllerNamespace(),
                'prefix' => ApiModule::getApiPrefix(),
            ],
            function () {
                Route::get(
                    'doc',
                    function () {
                        return app()->call(ApiDocumentationController::class . '@index');
                    }
                );

                Route::match(
                    ApiModule::getAvailableApiMethods(),
                    ApiModule::getApiUriPattern(),
                    function () {
                        return ApiModule::makeApi();
                    }
                )->middleware(ApiModule::getApiMiddleware());
            }
        );
    }
}
