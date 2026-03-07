<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Middlewares\RequestIdMiddleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

it('generates request id when header is absent', function () {
    $middleware = new RequestIdMiddleware();
    $request = Request::create('/test', 'GET');

    $response = $middleware->handle($request, fn () => new JsonResponse(['ok' => true]));

    $requestId = $response->headers->get('X-Request-Id');
    expect($requestId)->not->toBeNull();
    expect($requestId)->toMatch('/^[0-9a-f\-]{36}$/');
});

it('uses existing X-Request-Id header', function () {
    $middleware = new RequestIdMiddleware();
    $request = Request::create('/test', 'GET');
    $request->headers->set('X-Request-Id', 'custom-id-123');

    $response = $middleware->handle($request, fn () => new JsonResponse(['ok' => true]));

    expect($response->headers->get('X-Request-Id'))->toBe('custom-id-123');
});

it('passes response through unchanged', function () {
    $middleware = new RequestIdMiddleware();
    $request = Request::create('/test', 'GET');

    $response = $middleware->handle($request, fn () => new JsonResponse(['data' => 'value']));

    $data = $response->getData(true);
    expect($data['data'])->toBe('value');
});
