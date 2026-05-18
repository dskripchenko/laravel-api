<?php

namespace Dskripchenko\LaravelApi\Exceptions;

use Dskripchenko\LaravelApi\Services\ApiResponseHelper;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Closure;
use Exception;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
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

        $this->addErrorHandler(ValidationException::class, function (ValidationException $e) {
            return ApiResponseHelper::sayError([
                'errorKey' => 'validation_error',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], $e->status);
        });

        $this->addErrorHandler(HttpExceptionInterface::class, function (HttpExceptionInterface $e) {
            return ApiResponseHelper::sayError([
                'errorKey' => 'http_error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        });
    }

    /**
     * @param Throwable $e
     * @return Closure|false
     */
    protected function findHandler(Throwable $e): Closure|false
    {
        $className = get_class($e);
        $handler = Arr::get($this->handlers, $className, false);
        if ($handler) {
            return $handler;
        }

        foreach (class_parents($e) as $parent) {
            $handler = Arr::get($this->handlers, $parent, false);
            if ($handler) {
                return $handler;
            }
        }

        foreach (class_implements($e) as $interface) {
            $handler = Arr::get($this->handlers, $interface, false);
            if ($handler) {
                return $handler;
            }
        }

        return false;
    }

    /**
     * @param Throwable $e
     * @return mixed
     */
    public function handle(Throwable $e)
    {
        $handle = $this->findHandler($e);
        if (!$handle) {
            $handle = static function (Throwable $e) {
                $message = app()->hasDebugModeEnabled()
                    ? $e->getMessage()
                    : 'Internal server error';

                return ApiResponseHelper::sayError([
                    'message' => $message,
                ], 500);
            };
        }

        return $handle($e);
    }
}
