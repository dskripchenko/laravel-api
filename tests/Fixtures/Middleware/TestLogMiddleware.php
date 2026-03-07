<?php

declare(strict_types=1);

namespace Tests\Fixtures\Middleware;

use Closure;
use Dskripchenko\LaravelApi\Middlewares\ApiMiddleware;
use Illuminate\Http\Request;

class TestLogMiddleware extends ApiMiddleware
{
    public function run(Request $request, Closure $next)
    {
        return $next($request);
    }
}
