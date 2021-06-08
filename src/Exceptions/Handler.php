<?php

namespace Dskripchenko\LaravelApi\Exceptions;

use Dskripchenko\LaravelApi\Facades\ApiErrorHandler;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use \Symfony\Component\HttpFoundation\Response;
use \Illuminate\Http\Request;
use Exception;

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
     * @param \Throwable $e
     * @return Response
     *
     * @throws Exception
     */
    public function render($request, \Throwable  $e)
    {
        if ($request->routeIs('api-endpoint')) {
            return ApiErrorHandler::handle($e);
        }
        return parent::render($request, $e);
    }
}
