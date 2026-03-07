<?php

declare(strict_types=1);

it('resolves v1 correctly', function () {
    $response = $this->api('v1', 'open', 'ping');
    $response->assertStatus(200);
    $data = $response->json();
    expect($data['payload']['pong'])->toBeTrue();
});

it('resolves v2 correctly', function () {
    $response = $this->api('v2', 'item', 'show', ['id' => 1]);
    $response->assertStatus(200);
    $data = $response->json();
    expect($data['payload']['version'])->toBe('v2');
});

it('v2 inherits v1 actions', function () {
    // 'list' comes from v1 and should be available in v2
    $response = $this->api('v2', 'item', 'list');
    $response->assertStatus(200);
});

it('v2 overrides v1 action with different response', function () {
    // Test v2 first to avoid static cache pollution from v1
    $v2Response = $this->api('v2', 'item', 'show', ['id' => 1]);
    $v2Data = $v2Response->json();
    expect($v2Data['payload']['name'])->toBe('test-v2');
    expect($v2Data['payload']['version'])->toBe('v2');
});

it('v2 adds new actions not in v1', function () {
    $response = $this->api('v2', 'item', 'search', ['query' => 'test']);
    $response->assertStatus(200);
    $data = $response->json();
    expect($data['payload']['query'])->toBe('test');
});

it('v2 disables inherited action', function () {
    // 'remove' is disabled in v2 (set to false)
    // Handler catches NotFoundHttpException and returns error envelope
    $response = $this->api('v2', 'item', 'remove');
    $data = $response->json();
    expect($data['success'])->toBeFalse();
    expect($data['payload']['message'])->toContain('not found');
});

it('returns RuntimeException for invalid version', function () {
    expect(fn() => $this->api('v99', 'item', 'list'))
        ->toThrow(\RuntimeException::class);
});

it('v2 inherits open controller from v1', function () {
    $response = $this->api('v2', 'open', 'ping');
    $response->assertStatus(200);
});
