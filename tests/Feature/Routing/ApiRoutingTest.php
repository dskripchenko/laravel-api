<?php

declare(strict_types=1);

it('routes GET request to correct controller action', function () {
    $response = $this->api('v1', 'open', 'ping');
    $response->assertStatus(200);
    $data = $response->json();
    expect($data['success'])->toBeTrue();
    expect($data['payload']['pong'])->toBeTrue();
});

it('routes POST request to correct controller action', function () {
    $response = $this->api('v1', 'item', 'create', ['name' => 'New']);
    $response->assertStatus(200);
    $data = $response->json();
    expect($data['success'])->toBeTrue();
    expect($data['payload']['name'])->toBe('New');
});

it('returns error for non-existent controller', function () {
    $response = $this->call('POST', 'api/v1/nonexistent/action');
    $data = json_decode($response->getContent(), true);
    expect($data['success'])->toBeFalse();
    expect($data['payload']['message'])->toContain('not found');
});

it('returns error for non-existent action', function () {
    $response = $this->call('POST', 'api/v1/item/nonexistent');
    $data = json_decode($response->getContent(), true);
    expect($data['success'])->toBeFalse();
    expect($data['payload']['message'])->toContain('not found');
});

it('returns error for disabled action', function () {
    $response = $this->call('POST', 'api/v1/item/disabled');
    $data = json_decode($response->getContent(), true);
    expect($data['success'])->toBeFalse();
});

it('returns error for wrong HTTP method', function () {
    // 'list' only supports GET, sending POST should fail
    $response = $this->call('POST', 'api/v1/item/list');
    $data = json_decode($response->getContent(), true);
    expect($data['success'])->toBeFalse();
    expect($data['payload']['message'])->toContain('not supported');
});

it('handles aliased action', function () {
    $response = $this->api('v1', 'item', 'remove');
    $response->assertStatus(200);
    $data = $response->json();
    expect($data['payload']['deleted'])->toBeTrue();
});

it('returns success envelope format', function () {
    $response = $this->api('v1', 'open', 'ping');
    $data = $response->json();
    expect($data)->toHaveKeys(['success', 'payload']);
    expect($data['success'])->toBeTrue();
});

it('passes request data to controller', function () {
    $response = $this->api('v1', 'item', 'create', ['name' => 'TestItem']);
    $data = $response->json();
    expect($data['payload']['name'])->toBe('TestItem');
});

it('supports GET method on show action', function () {
    $response = $this->api('v1', 'item', 'show', ['id' => 42]);
    $response->assertStatus(200);
    $data = $response->json();
    expect($data['payload']['name'])->toBe('test');
});

it('registers api-doc route', function () {
    $routes = app('router')->getRoutes();
    $route = $routes->getByName('api-doc');
    expect($route)->not->toBeNull();
});

it('supports update with POST method', function () {
    $response = $this->api('v1', 'item', 'update', ['id' => 5, 'name' => 'Updated']);
    $response->assertStatus(200);
    $data = $response->json();
    expect($data['payload']['name'])->toBe('Updated');
});

it('handles list action', function () {
    $response = $this->api('v1', 'item', 'list');
    $response->assertStatus(200);
    $data = $response->json();
    expect($data['payload'])->toHaveKey('items');
    expect($data['payload'])->toHaveKey('total');
});

it('registers api-endpoint route name', function () {
    $routes = app('router')->getRoutes();
    $route = $routes->getByName('api-endpoint');
    expect($route)->not->toBeNull();
});
