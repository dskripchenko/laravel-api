<?php

namespace Dskripchenko\LaravelApi\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string getApiVersion()
 * @method static string getApiMethod()
 * @method static string getApiControllerKey()
 * @method static string getApiActionKey()
 * @method static array all()
 *
 * @see \Dskripchenko\LaravelApi\Components\BaseApiRequest
 */
class ApiRequest extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'api_request';
    }
}
