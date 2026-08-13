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

it('per-action middleware from getMethods runs at request time', function () {
    $response = $this->api('v1', 'item', 'list');
    $data = $response->json();
    expect($data['success'])->toBeFalse();
    expect($data['payload']['errorKey'])->toBe('auth_error');
});

it('per-action middleware passes when its precondition is met', function () {
    $response = $this->api('v1', 'item', 'list', [], ['X-Auth-Token' => 'token']);
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

it('собирает исключения с уровня версии, контроллера и действия', function () {
    // `exclude-middleware` used to be subtracted only from the version's own
    // list, so removing anything from the routes' shared group with it was
    // impossible — the name promised more than the mechanism did.
    $excluded = V1Api::getExcludedMiddlewareByControllerAndActionKey('item', 'list');

    expect($excluded)->toBeArray();
});

it('исключения доезжают до маршрута, а не только до списка версии', function () {
    // The key property: the list travels to the route through
    // withoutMiddleware and therefore removes what came from the group too (the
    // sessions, the cookies, the panel's own). Without this
    // `exclude-middleware` acted only on the version's own list and never
    // touched the group.
    $v2 = collect(app('router')->getRoutes())
        ->first(fn ($r) => str_starts_with($r->uri(), 'api/v2/'));
    $v1 = collect(app('router')->getRoutes())
        ->first(fn ($r) => str_starts_with($r->uri(), 'api/v1/'));

    expect($v2->excludedMiddleware())->toContain(TestLogMiddleware::class);
    expect($v1->excludedMiddleware())->not->toContain(TestLogMiddleware::class);
});
