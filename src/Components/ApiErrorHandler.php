<?php


namespace Dskripchenko\LaravelApi\Components;


use Illuminate\Support\Arr;

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
