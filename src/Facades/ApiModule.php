<?php

namespace Dskripchenko\LaravelApi\Facades;

use Dskripchenko\LaravelApi\Components\BaseApi;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string getApiPrefix()
 * @method static array getAvailableApiMethods()
 * @method static string getApiUriPattern()
 * @method static array getApiMiddleware()
 * @method static array getApiVersionList()
 * @method static mixed makeApi()
 * @method static BaseApi|null getApi(string $version = null)
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
