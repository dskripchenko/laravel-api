<?php

declare(strict_types=1);

namespace Tests\Fixtures\Middleware;

use Closure;
use Dskripchenko\LaravelApi\Exceptions\ApiException;
use Dskripchenko\LaravelApi\Middlewares\ApiMiddleware;
use Illuminate\Http\Request;

class TestAuthMiddleware extends ApiMiddleware
{
    /**
     * @input string $token Auth token
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     * @throws ApiException
     */
    public function run(Request $request, Closure $next)
    {
        $token = $request->header('X-Auth-Token');
        if (!$token) {
            throw new ApiException('auth_error', 'Token required');
        }

        return $next($request);
    }
}
