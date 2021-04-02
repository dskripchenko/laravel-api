<?php

namespace Dskripchenko\LaravelApi\Middlewares;

use Dskripchenko\LaravelApi\Exceptions\ApiException;
use Dskripchenko\LaravelApi\Services\ApiResponseHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

/**
 * Class ApiMiddleware
 * @package Dskripchenko\LaravelApi\Middlewares
 */
abstract class ApiMiddleware
{
    /**
     * @param Request $request
     * @param \Closure $next
     * @return JsonResponse|mixed
     */
    public function handle(Request $request, \Closure $next): JsonResponse
    {
        try {
            return $this->run($request, $next);
        }
        catch (ApiException $e){
            return ApiResponseHelper::sayError([
                'errorKey' => $e->getErrorKey(),
                'message' => $e->getMessage(),
            ]);
        }
        catch (Exception $e){
            return ApiResponseHelper::sayError([
                'errorKey' => $e->getCode(),
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param Request $request
     * @param \Closure $next
     * @return mixed
     */
    abstract public function run(Request $request, \Closure $next);
}
