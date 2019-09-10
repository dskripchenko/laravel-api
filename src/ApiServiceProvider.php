<?php


namespace Dskripchenko\LaravelApi\Providers;


use Dskripchenko\LaravelApi\Components\BaseApiRequest;
use Dskripchenko\LaravelApi\Facades\ApiModule;
use Dskripchenko\LaravelApi\Components\ApiDocumentationController;
use Dskripchenko\LaravelApi\Components\BaseModule;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Route;

class ApiServiceProvider extends RouteServiceProvider
{

    public function boot()
    {
        parent::boot();

        $this->loadViewsFrom(dirname(__DIR__) . '/resources/views', 'api_module');
    }

    public function register()
    {
        $this->app->bind('api_module', function (){
            return new BaseModule();
        });

        $this->app->bind('api_request', function (){
            return BaseApiRequest::getInstance();
        });

        parent::register();
    }

    public function map()
    {
        Route::group([
            'namespace' => ApiModule::getControllerNamespace(),
            'prefix' => ApiModule::getApiPrefix(),
        ], function (){
            Route::get('doc',function (){
                return app()->call(ApiDocumentationController::class . '@index');
            });

            Route::match(ApiModule::getAvailableApiMethods(), ApiModule::getApiUriPattern(), function (){
                return ApiModule::makeApi();
            })->middleware(ApiModule::getApiMiddleware());
        });
    }
}