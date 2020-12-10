<?php


namespace Dskripchenko\LaravelApi\Components;


use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ApiErrorHandler
{
    protected $handlers = [];

    /**
     * @param $exceptionClassName
     * @param \Closure $handler
     */
    public function addErrorHandler($exceptionClassName, \Closure $handler)
    {
        $this->handlers[$exceptionClassName] = $handler;
    }

    /**
     * ApiErrorHandler constructor.
     */
    public function __construct()
    {
        $this->addErrorHandler(
            ApiException::class,
            function (ApiException $e) {
                return ApiResponseHelper::sayError(
                    [
                        'errorKey' => $e->getErrorKey(),
                        'message' => $e->getMessage(),
                    ]
                );
            }
        );

        $this->addErrorHandler(
            ValidationException::class,
            function (ValidationException $e) {
                return ApiResponseHelper::sayError(
                    [
                        'errorKey' => 'validation',
                        'messages' => $e->errors()
                    ]
                );
            }
        );

        $this->addErrorHandler(
            AuthorizationException::class,
            function (AuthorizationException $e) {
                return ApiResponseHelper::sayError(
                    [
                        'errorKey' => 'forbidden',
                        'message'  => $e->getMessage()
                    ]
                );
            }
        );

        $this->addErrorHandler(
            AuthenticationException::class,
            function (AuthenticationException $e) {
                return ApiResponseHelper::sayError(
                    [
                        'errorKey' => 'forbidden',
                        'message'  => $e->getMessage()
                    ]
                );
            }
        );

        $this->addErrorHandler(
            ModelNotFoundException::class,
            function (ModelNotFoundException $e) {
                return ApiResponseHelper::sayError(
                    [
                        'errorKey' => 'not_found',
                        'message'  => "Not found"
                    ]
                );
            }
        );

    }

    /**
     * @param \Exception $e
     * @return mixed
     */
    public function handle(\Exception $e)
    {
        $className = get_class($e);
        $handle = Arr::get($this->handlers, $className, false);
        if (!$handle) {
            $handle = function (\Exception $e) {
                return ApiResponseHelper::sayError(
                    [
                        'errorKey' => $e->getCode(),
                        'message' => $e->getMessage(),
                    ]
                );
            };
        }

        return $handle($e);
    }
}
