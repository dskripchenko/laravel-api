<?php

declare(strict_types=1);

namespace Tests\Fixtures\OpenApi;

use Closure;
use Dskripchenko\LaravelApi\Middlewares\ApiMiddleware;
use Illuminate\Http\Request;

class HeaderMiddleware extends ApiMiddleware
{
    /**
     * @header string $X-Auth-Token Auth token from middleware
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function run(Request $request, Closure $next)
    {
        return $next($request);
    }
}
