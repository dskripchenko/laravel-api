<?php

declare(strict_types=1);

namespace Tests\Fixtures\Linter;

use Closure;

/**
 * Exists only so that a parameterised middleware entry has a real class behind
 * it.
 */
class PassingMiddleware
{
    public function handle($request, Closure $next, ?string $permission = null)
    {
        return $next($request);
    }
}
