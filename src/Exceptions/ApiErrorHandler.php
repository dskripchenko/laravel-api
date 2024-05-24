<?php

namespace Dskripchenko\LaravelApi\Exceptions;

use Dskripchenko\LaravelApi\Services\ApiResponseHelper;
use Illuminate\Support\Arr;
use Closure;
use Exception;
use Throwable;

/**
 * Class ApiErrorHandler
 * @package Dskripchenko\LaravelApi\Exceptions
 */
class ApiErrorHandler
{
    protected $handlers = [];

    /**
     * @param $exceptionClassName
     * @param Closure $handler
     */
    public function addErrorHandler($exceptionClassName, Closure $handler): void
    {
        $this->handlers[$exceptionClassName] = $handler;
    }

    /**
     * ApiErrorHandler constructor.
     */
    public function __construct()
    {
        $this->addErrorHandler(ApiException::class, function (ApiException $e) {
            return ApiResponseHelper::sayError([
                'errorKey' => $e->getErrorKey(),
                'message' => $e->getMessage(),
            ]);
        });
    }

    /**
     * @param Throwable $e
     * @return mixed
     */
    public function handle(Throwable $e)
    {
        $className = get_class($e);
        $handle    = Arr::get($this->handlers, $className, false);
        if (!$handle) {
            $handle = static function (Throwable $e) {
                return ApiResponseHelper::sayError([
                    'message' => $e->getMessage(),
                ]);
            };
        }

        return $handle($e);
    }
}
