<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Facades\ApiRequest;
use Tests\Fixtures\Versions\v1\TestApi as V1Api;
use Tests\Fixtures\Versions\v2\TestApi as V2Api;
use Tests\Fixtures\Middleware\TestAuthMiddleware;
use Tests\Fixtures\Middleware\TestLogMiddleware;

it('resolves global middleware', function () {
    ApiRequest::shouldReceive('getApiControllerKey')->andReturn('open');
    ApiRequest::shouldReceive('getApiActionKey')->andReturn('ping');

    $middleware = V1Api::getMiddleware();
    expect($middleware)->toContain(TestLogMiddleware::class);
});

it('resolves controller-level middleware', function () {
    ApiRequest::shouldReceive('getApiControllerKey')->andReturn('item');
    ApiRequest::shouldReceive('getApiActionKey')->andReturn('list');

    $middleware = V1Api::getMiddleware();
    expect($middleware)->toContain(TestAuthMiddleware::class);
    expect($middleware)->toContain(TestLogMiddleware::class);
});

it('controller without middleware only has global', function () {
    ApiRequest::shouldReceive('getApiControllerKey')->andReturn('open');
    ApiRequest::shouldReceive('getApiActionKey')->andReturn('ping');

    $middleware = V1Api::getMiddleware();
    expect($middleware)->toContain(TestLogMiddleware::class);
    expect($middleware)->not->toContain(TestAuthMiddleware::class);
});

it('v2 inherits middleware configuration from v1', function () {
    ApiRequest::shouldReceive('getApiControllerKey')->andReturn('item');
    ApiRequest::shouldReceive('getApiActionKey')->andReturn('list');

    $middleware = V2Api::getMiddleware();
    expect($middleware)->toContain(TestLogMiddleware::class);
    expect($middleware)->toContain(TestAuthMiddleware::class);
});

it('returns empty middleware for non-existent action', function () {
    ApiRequest::shouldReceive('getApiControllerKey')->andReturn('nonexistent');
    ApiRequest::shouldReceive('getApiActionKey')->andReturn('missing');

    $middleware = V1Api::getMiddleware();
    expect($middleware)->toBe([]);
});

it('api routes process requests without enforcement of getMethods middleware', function () {
    // Middleware in getMethods is metadata, not enforced during request
    $response = $this->api('v1', 'item', 'list');
    $response->assertStatus(200);
    $data = $response->json();
    expect($data['success'])->toBeTrue();
});

it('open controller routes work', function () {
    $response = $this->api('v1', 'open', 'ping');
    $response->assertStatus(200);
    $data = $response->json();
    expect($data['success'])->toBeTrue();
    expect($data['payload']['pong'])->toBeTrue();
});

it('v2 routes work with inherited actions', function () {
    $response = $this->api('v2', 'item', 'list', [], ['X-Auth-Token' => 'token']);
    $response->assertStatus(200);
    $data = $response->json();
    expect($data['success'])->toBeTrue();
});
