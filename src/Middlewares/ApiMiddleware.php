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
            // An ApiException above is a refusal the application meant to give;
            // this one is not. Until now it produced a 500 whose body says
            // nothing and left no trace anywhere: not in the log, not in the
            // exception handler, nowhere. Whoever met it had the response and
            // nothing else. `report()` goes through the framework's handler, so
            // `dontReport` and every logging channel keep working as declared.
            report($e);

            $message = app()->hasDebugModeEnabled()
                ? $e->getMessage()
                : 'Internal server error';

            return ApiResponseHelper::sayError([
                'errorKey' => (string) $e->getCode(),
                'message' => $message,
            ], 500);
        }
    }

    /**
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    abstract public function run(Request $request, Closure $next);
}
