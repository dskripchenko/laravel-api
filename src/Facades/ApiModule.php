<?php


namespace Dskripchenko\LaravelApi\Facades;


use Illuminate\Support\Facades\Facade;


/**
 * @method static string getApiPrefix()
 * @method static string getAvailableApiMethods()
 * @method static string getApiUriPattern()
 * @method static string getControllerNamespace()
 * @method static array getApiMiddleware()
 * @method static array getApiVersionList()
 * @method static mixed makeApi()
 *
 * @see \Dskripchenko\LaravelApi\Components\BaseModule
 */
class ApiModule extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'api_module';
    }
}