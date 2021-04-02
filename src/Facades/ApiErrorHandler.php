<?php

namespace Dskripchenko\LaravelApi\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void addErrorHandler($exceptionClassName, \Closure $handler)
 * @method static mixed handle(\Exception $e)
 *
 * @see \Dskripchenko\LaravelApi\Components\ApiErrorHandler
 */
class ApiErrorHandler extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'api_error_handler';
    }
}
