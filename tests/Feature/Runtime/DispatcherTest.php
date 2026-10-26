<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Facades\ApiRequest;
use Dskripchenko\LaravelApi\Runtime\ApiInvocation;
use Dskripchenko\LaravelApi\Runtime\ApiResult;
use Dskripchenko\LaravelApi\Runtime\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

function dispatchApi(string $controller, string $action, array $params = [], array $headers = [], string $version = 'v1', ?string $method = null): ApiResult
{
    return app(Dispatcher::class)->dispatch(new ApiInvocation($version, $controller, $action, $params, $headers, $method));
}

it('calls an action and reads its answer', function () {
    $result = dispatchApi('open', 'ping');

    expect($result->success)->toBeTrue();
    expect($result->status)->toBe(200);
    expect($result->payload)->toBe(['pong' => true]);
});

it('runs the middleware cascade, as over HTTP', function () {
    $refused = dispatchApi('item', 'list');

    expect($refused->success)->toBeFalse();
    expect($refused->errorKey())->toBe('auth_error');
    expect($refused->message())->toBe('Token required');

    expect(dispatchApi('item', 'list', [], ['X-Auth-Token' => 'token'])->success)->toBeTrue();
});

it('passes the input, in the query for GET and the body otherwise', function () {
    $token = ['X-Auth-Token' => 'token'];

    expect(dispatchApi('item', 'show', ['id' => 7], $token)->payload)->toBe(['id' => '7', 'name' => 'test']);
    expect(dispatchApi('item', 'create', ['name' => 'New'], $token)->payload)->toBe(['id' => 1, 'name' => 'New']);
});

it('calls with the action\'s own HTTP method unless told otherwise', function () {
    // ping answers GET only.
    expect(dispatchApi('open', 'ping')->success)->toBeTrue();

    $wrong = dispatchApi('open', 'ping', method: 'post');
    expect($wrong->success)->toBeFalse();
    expect($wrong->status)->toBe(404);
    expect($wrong->errorKey())->toBe('http_error');
});

it('answers an unknown action and an inactive version the way HTTP does', function () {
    $action = dispatchApi('open', 'nope');
    expect([$action->status, $action->errorKey()])->toBe([404, 'http_error']);

    $version = dispatchApi('open', 'ping', version: 'v9');
    expect([$version->status, $version->errorKey()])->toBe([404, 'http_error']);
    expect($version->message())->toBe('The requested version is not active!');
});

it('runs inside an HTTP request, as that caller, and gives the request back', function () {
    Route::get('/outer', static function () {
        $result = dispatchApi('item', 'show', ['id' => 3]);

        return new JsonResponse([
            'inner' => [$result->success, $result->payload],
            'request' => request()->path(),
            'route' => Route::current()?->uri(),
            'input' => request()->input('marker'),
            'apiRequest' => ApiRequest::input('marker'),
        ]);
    });

    $this->getJson('/outer?marker=outer', ['X-Auth-Token' => 'token'])
        ->assertOk()
        ->assertExactJson([
            // Authenticated by the header of the request it ran in.
            'inner' => [true, ['id' => '3', 'name' => 'test']],
            'request' => 'outer',
            'route' => 'outer',
            'input' => 'outer',
            'apiRequest' => 'outer',
        ]);
});

it('reads an answer without the envelope, and one that is not JSON', function () {
    $plain = ApiResult::fromResponse(new JsonResponse(['id' => 1], 201));
    expect([$plain->success, $plain->payload, $plain->status])->toBe([true, ['id' => 1], 201]);

    $text = ApiResult::fromResponse(new Response('nope', 503));
    expect([$text->success, $text->payload, $text->status])->toBe([false, 'nope', 503]);

    $empty = ApiResult::fromResponse(new JsonResponse(null, 204));
    expect([$empty->success, $empty->status])->toBe([true, 204]);
});
