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
    $response = $this->get(route('api.v1.item.list'), ['X-Auth-Token' => 'token']);
    $response->assertStatus(200);
    $data = $response->json();
    expect($data['success'])->toBeTrue();

    $response = $this->post(route('api.v1.item.create'), ['name' => 'Test'], ['X-Auth-Token' => 'token']);
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

    $response = $this->post($url, [], ['X-Auth-Token' => 'token']);
    $response->assertStatus(200);
    $data = $response->json();
    expect($data['payload']['deleted'])->toBeTrue();
});

it('v2 named routes work with inherited actions', function () {
    // v2 inherits 'list' from v1
    $response = $this->get(route('api.v2.item.list'), ['X-Auth-Token' => 'token']);
    $response->assertStatus(200);
    $data = $response->json();
    expect($data['success'])->toBeTrue();
});

it('does not carry the middleware of the request the routes were built in to other actions', function () {
    // A long-lived process (Octane, a test suite) builds the routes once, in
    // whatever request came first. Here that is item.list, guarded by
    // TestAuthMiddleware; open.ping has no such guard and must stay open.
    \Dskripchenko\LaravelApi\Requests\BaseApiRequest::createFromBase(
        \Symfony\Component\HttpFoundation\Request::create('/api/v1/item/list', 'GET')
    );
    app()->getProvider(\Tests\Fixtures\TestServiceProvider::class)->makeApiRoutes();
    app('router')->getRoutes()->refreshNameLookups();

    $response = $this->get(route('api.v1.open.ping'));

    $response->assertStatus(200);
    expect($response->json('payload.pong'))->toBeTrue();
});

it('gives each named route only its own middleware cascade', function () {
    $route = app('router')->getRoutes()->getByName('api.v1.open.ping');

    expect($route->middleware())->not->toContain('api-middleware-group')
        ->and($route->middleware())->not->toContain(\Tests\Fixtures\Middleware\TestAuthMiddleware::class);
});
