<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Exceptions\ApiException;
use Dskripchenko\LaravelApi\Middlewares\ApiMiddleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

it('passes through on success', function () {
    $middleware = new class extends ApiMiddleware {
        public function run(Request $request, Closure $next)
        {
            return $next($request);
        }
    };

    $request = Request::create('/test', 'GET');
    $response = $middleware->handle($request, fn() => new JsonResponse(['ok' => true]));
    expect($response->getData(true))->toBe(['ok' => true]);
});

it('catches ApiException and returns error response with errorKey', function () {
    $middleware = new class extends ApiMiddleware {
        public function run(Request $request, Closure $next)
        {
            throw new ApiException('auth_error', 'Not authorized');
        }
    };

    $request = Request::create('/test', 'GET');
    $response = $middleware->handle($request, fn() => new JsonResponse());
    $data = $response->getData(true);
    expect($data['success'])->toBeFalse();
    expect($data['payload']['errorKey'])->toBe('auth_error');
    expect($data['payload']['message'])->toBe('Not authorized');
});

it('catches generic Exception and returns error with debug message in debug mode', function () {
    app()['config']->set('app.debug', true);
    $middleware = new class extends ApiMiddleware {
        public function run(Request $request, Closure $next)
        {
            throw new \RuntimeException('Something failed', 500);
        }
    };

    $request = Request::create('/test', 'GET');
    $response = $middleware->handle($request, fn() => new JsonResponse());
    $data = $response->getData(true);
    expect($data['success'])->toBeFalse();
    expect($data['payload']['errorKey'])->toBe(500);
    expect($data['payload']['message'])->toBe('Something failed');
    expect($response->getStatusCode())->toBe(500);
});

it('hides exception message in production mode for generic exceptions', function () {
    app()['config']->set('app.debug', false);
    $middleware = new class extends ApiMiddleware {
        public function run(Request $request, Closure $next)
        {
            throw new \RuntimeException('SELECT * FROM users WHERE id=1', 500);
        }
    };

    $request = Request::create('/test', 'GET');
    $response = $middleware->handle($request, fn() => new JsonResponse());
    $data = $response->getData(true);
    expect($data['success'])->toBeFalse();
    expect($data['payload']['message'])->toBe('Internal server error');
    expect($data['payload']['message'])->not->toContain('SELECT');
    expect($response->getStatusCode())->toBe(500);
});

it('does not catch Error (TypeError)', function () {
    $middleware = new class extends ApiMiddleware {
        public function run(Request $request, Closure $next)
        {
            throw new \TypeError('Type error');
        }
    };

    $request = Request::create('/test', 'GET');
    expect(fn() => $middleware->handle($request, fn() => new JsonResponse()))
        ->toThrow(\TypeError::class);
});
