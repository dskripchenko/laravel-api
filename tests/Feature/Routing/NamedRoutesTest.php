<?php

declare(strict_types=1);

it('registers named routes for all actions', function () {
    $routes = app('router')->getRoutes();

    expect($routes->getByName('api.v1.item.list'))->not->toBeNull();
    expect($routes->getByName('api.v1.item.show'))->not->toBeNull();
    expect($routes->getByName('api.v1.item.create'))->not->toBeNull();
    expect($routes->getByName('api.v1.item.update'))->not->toBeNull();
    expect($routes->getByName('api.v1.open.ping'))->not->toBeNull();
    expect($routes->getByName('api.v2.item.list'))->not->toBeNull();
    expect($routes->getByName('api.v2.item.search'))->not->toBeNull();
});

it('does not register named routes for disabled actions', function () {
    $routes = app('router')->getRoutes();

    expect($routes->getByName('api.v1.item.disabled'))->toBeNull();
    expect($routes->getByName('api.v2.item.remove'))->toBeNull();
});

it('keeps catch-all api-endpoint route', function () {
    $routes = app('router')->getRoutes();

    expect($routes->getByName('api-endpoint'))->not->toBeNull();
});

it('named routes resolve to correct URLs', function () {
    $url = route('api.v1.item.list');
    expect($url)->toContain('/api/v1/item/list');

    $url = route('api.v2.item.search');
    expect($url)->toContain('/api/v2/item/search');

    $url = route('api.v1.open.ping');
    expect($url)->toContain('/api/v1/open/ping');
});

it('named routes handle requests correctly', function () {
    $response = $this->get(route('api.v1.open.ping'));
    $response->assertStatus(200);
    $data = $response->json();
    expect($data['success'])->toBeTrue();
    expect($data['payload']['pong'])->toBeTrue();
});

it('named routes respect HTTP methods', function () {
    $response = $this->get(route('api.v1.item.list'));
    $response->assertStatus(200);
    $data = $response->json();
    expect($data['success'])->toBeTrue();

    $response = $this->post(route('api.v1.item.create'), ['name' => 'Test']);
    $response->assertStatus(200);
    $data = $response->json();
    expect($data['success'])->toBeTrue();
});

it('catch-all still works for unregistered patterns', function () {
    // The catch-all route should still handle valid requests
    $response = $this->call('GET', '/api/v1/open/ping');
    $response->assertStatus(200);
    $data = $response->json();
    expect($data['success'])->toBeTrue();
});

it('aliased action routes are accessible by name', function () {
    $url = route('api.v1.item.remove');
    expect($url)->toContain('/api/v1/item/remove');

    $response = $this->post($url);
    $response->assertStatus(200);
    $data = $response->json();
    expect($data['payload']['deleted'])->toBeTrue();
});

it('v2 named routes work with inherited actions', function () {
    // v2 inherits 'list' from v1
    $response = $this->get(route('api.v2.item.list'));
    $response->assertStatus(200);
    $data = $response->json();
    expect($data['success'])->toBeTrue();
});
