<?php

namespace Dskripchenko\LaravelApi\Exceptions;

use Dskripchenko\LaravelApi\Facades\ApiErrorHandler;
use Dskripchenko\LaravelApi\Providers\ApiServiceProvider;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Exception;
use Throwable;

/**
 * Class Handler
 * @package Dskripchenko\LaravelApi\Exceptions
 */
class Handler extends ExceptionHandler
{
    /**
     * Render an exception into an HTTP response.
     *
     * @param Request $request
     * @param Throwable $e
     * @return Response
     *
     * @throws Exception
     */
    public function render($request, Throwable $e)
    {
        if (ApiServiceProvider::isApiRoute($request->route()) || $request->routeIs('api-endpoint')) {
            $this->report($e);

            // What no handler knows, Laravel may still know how to answer: a
            // CSRF mismatch is 419, a missing model 404. Its own conversion
            // goes first, so these do not become a bare 500.
            if (!ApiErrorHandler::handles($e)) {
                $e = $this->prepareException($e);
            }

            return ApiErrorHandler::handle($e);
        }
        return parent::render($request, $e);
    }
}
