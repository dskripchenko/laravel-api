<?php

namespace Dskripchenko\LaravelApi\Middlewares;

use Closure;
use Dskripchenko\LaravelApi\Exceptions\ApiException;
use Dskripchenko\LaravelApi\Services\ApiResponseHelper;
use Symfony\Component\HttpFoundation\Response;
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
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $this->run($request, $next);
        } catch (ApiException $e) {
            return ApiResponseHelper::sayError([
                'errorKey' => $e->getErrorKey(),
                'message' => $e->getMessage(),
            ]);
        } catch (Exception $e) {
            return ApiResponseHelper::sayError([
                'errorKey' => $e->getCode(),
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    abstract public function run(Request $request, Closure $next);
}
