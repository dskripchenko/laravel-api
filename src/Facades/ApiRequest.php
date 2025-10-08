<?php

namespace Dskripchenko\LaravelApi\Facades;

use Dskripchenko\LaravelApi\Requests\BaseApiRequest;
use Illuminate\Support\Facades\Facade;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * @method static string getApiVersion()
 * @method static string getApiMethod()
 * @method static string getApiControllerKey()
 * @method static string getApiActionKey()
 * @method static array all()
 * @method static string method()
 * @method static BaseApiRequest|null getInstance()
 * @method static BaseApiRequest|null createFromBase(SymfonyRequest $request)
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
