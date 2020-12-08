<?php


namespace Dskripchenko\LaravelApi\Components;


use Illuminate\Http\Request;

abstract class ApiMiddleware
{
    /**
     * @param Request $request
     * @param \Closure $next
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    final public function handle(Request $request, \Closure $next)
    {
        try {
            return $this->run($request, $next);
        } catch (ApiException $e) {
            return ApiResponseHelper::sayError(
                [
                    'errorKey' => $e->getErrorKey(),
                    'message' => $e->getMessage(),
                ]
            );
        } catch (\Exception $e) {
            return ApiResponseHelper::sayError(
                [
                    'errorKey' => $e->getCode(),
                    'message' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * @param Request $request
     * @param \Closure $next
     * @return mixed
     */
    abstract public function run(Request $request, \Closure $next);
}
