<?php

namespace Dskripchenko\LaravelApi\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use Throwable;

/**
 * @method static void addErrorHandler($exceptionClassName, Closure $handler)
 * @method static mixed handle(Throwable $e)
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
