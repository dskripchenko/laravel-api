<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApi\Components\BaseModule;
use Dskripchenko\LaravelApi\Controllers\ApiController;
use Dskripchenko\LaravelApi\Facades\ApiModule;
use Dskripchenko\LaravelApi\Providers\ApiServiceProvider;
use Dskripchenko\LaravelApi\Transports\JsonRpc\JsonRpcServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

function enableJsonRpc(array $config = []): void
{
    config(['laravel-api.jsonrpc' => array_merge(config('laravel-api.jsonrpc'), ['enabled' => true], $config)]);
    Route::prefix(ApiModule::getApiPrefix())->group(static fn () => ApiServiceProvider::registerJsonRpcRoutes());
    Route::getRoutes()->refreshNameLookups();
}

function rpc(object $test, mixed $body, array $headers = [], string $version = 'v1'): TestResponse
{
    return $test->postJson("/api/rpc/{$version}", $body, $headers);
}

function request_(string $method, array $params = [], int|string|null $id = 1): array
{
    $message = ['jsonrpc' => '2.0', 'method' => $method, 'params' => $params];
    if ($id !== null) {
        $message['id'] = $id;
    }

    return $message;
}

it('is off unless enabled', function () {
    rpc($this, request_('open.ping'))->assertNotFound();
});

it('calls an action by name and answers with its payload', function () {
    enableJsonRpc();

    rpc($this, request_('open.ping'))
        ->assertOk()
        ->assertExactJson(['jsonrpc' => '2.0', 'result' => ['pong' => true], 'id' => 1]);
});

it('passes the params by name, and the headers of the request', function () {
    enableJsonRpc();

    rpc($this, request_('item.create', ['name' => 'New'], 'a'), ['X-Auth-Token' => 'token'])
        ->assertExactJson(['jsonrpc' => '2.0', 'result' => ['id' => 1, 'name' => 'New'], 'id' => 'a']);
});

it('answers a failed action with an error carrying the status and the payload', function () {
    enableJsonRpc();

    rpc($this, request_('item.list'))->assertExactJson([
        'jsonrpc' => '2.0',
        'error' => [
            'code' => JsonRpcServer::ACTION_FAILED,
            'message' => 'Token required',
            'data' => ['status' => 200, 'payload' => ['errorKey' => 'auth_error', 'message' => 'Token required']],
        ],
        'id' => 1,
    ]);
});

it('answers protocol mistakes with the codes JSON-RPC defines', function () {
    enableJsonRpc();

    expect(rpc($this, request_('item.nope'))->json('error.code'))->toBe(JsonRpcServer::METHOD_NOT_FOUND);
    expect(rpc($this, request_('open.ping', ['positional']))->json('error.code'))->toBe(JsonRpcServer::INVALID_PARAMS);
    expect(rpc($this, ['jsonrpc' => '1.0', 'id' => 1, 'method' => 'open.ping'])->json('error.code'))->toBe(JsonRpcServer::INVALID_REQUEST);
    expect(rpc($this, [])->json('error.code'))->toBe(JsonRpcServer::INVALID_REQUEST);

    $parse = $this->call('POST', '/api/rpc/v1', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{nope');
    $parse->assertOk();
    expect($parse->json())->toMatchArray(['jsonrpc' => '2.0', 'id' => null]);
    expect($parse->json('error.code'))->toBe(JsonRpcServer::PARSE_ERROR);
});

it('answers a batch in order, leaving the notifications out', function () {
    enableJsonRpc();

    $response = rpc($this, [
        request_('open.ping', [], 1),
        request_('open.ping', [], null),
        request_('item.nope', [], 2),
        42,
    ]);

    expect($response->json())->toBe([
        ['jsonrpc' => '2.0', 'result' => ['pong' => true], 'id' => 1],
        ['jsonrpc' => '2.0', 'error' => ['code' => JsonRpcServer::METHOD_NOT_FOUND, 'message' => 'Method not found: item.nope'], 'id' => 2],
        ['jsonrpc' => '2.0', 'error' => ['code' => JsonRpcServer::INVALID_REQUEST, 'message' => 'Not a JSON-RPC 2.0 request.'], 'id' => null],
    ]);
});

it('answers nothing to notifications alone', function () {
    enableJsonRpc();

    rpc($this, request_('open.ping', [], null))->assertNoContent();
    rpc($this, [request_('open.ping', [], null), request_('open.ping', [], null)])->assertNoContent();
});

it('serves the versions it is told to', function () {
    enableJsonRpc(['versions' => ['v2']]);

    rpc($this, request_('open.ping'))->assertNotFound();
    rpc($this, request_('open.ping'), version: 'v2')->assertOk();
});

it('describes the version as an OpenRPC document', function () {
    enableJsonRpc();

    $document = rpc($this, request_('rpc.discover'))->json('result');

    expect($document['openrpc'])->toBe('1.3.2');
    expect($document['info'])->toBe(['title' => 'Test API v1', 'version' => 'v1', 'description' => 'Test API description']);

    $create = collect($document['methods'])->firstWhere('name', 'item.create');
    expect($create['summary'])->toBe('Create item');
    expect($create['paramStructure'])->toBe('by-name');
    expect(collect($create['params'])->firstWhere('name', 'name'))->toBe([
        'name' => 'name', 'description' => 'Item name', 'required' => true, 'schema' => ['type' => 'string'],
    ]);
    expect($create['result']['name'])->toBe('result');

    expect(array_column($document['methods'], 'name'))->toContain('open.ping', 'item.list');
});

// --- A validation failure ----------------------------------------------------

class JsonRpcValidatingController extends ApiController
{
    /**
     * Register
     *
     * @input string $email Email
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        return $this->success(['email' => $request->input('email')]);
    }
}

class JsonRpcValidatingApi extends BaseApi
{
    public static function getMethods(): array
    {
        return ['controllers' => [
            'user' => ['controller' => JsonRpcValidatingController::class, 'actions' => ['register']],
            'hidden' => ['controller' => JsonRpcValidatingController::class, 'jsonrpc' => false, 'actions' => ['register']],
        ]];
    }
}

class JsonRpcValidatingModule extends BaseModule
{
    public function getApiVersionList(): array
    {
        return ['v1' => JsonRpcValidatingApi::class];
    }
}

it('answers a validation failure as invalid params', function () {
    app()->bind('api_module', static fn () => new JsonRpcValidatingModule());
    ApiModule::clearResolvedInstance('api_module');
    (new ApiServiceProvider(app()))->makeApiRoutes();
    enableJsonRpc();

    $failed = rpc($this, request_('user.register', ['email' => 'not-an-email']));
    expect($failed->json('error.code'))->toBe(JsonRpcServer::INVALID_PARAMS);
    expect($failed->json('error.data.status'))->toBe(422);
    expect($failed->json('error.data.payload.errors'))->toHaveKey('email');

    expect(rpc($this, request_('user.register', ['email' => 'a@b.c']))->json('result'))->toBe(['email' => 'a@b.c']);

    // Left out with 'jsonrpc' => false.
    expect(rpc($this, request_('hidden.register', ['email' => 'a@b.c']))->json('error.code'))->toBe(JsonRpcServer::METHOD_NOT_FOUND);
});

it('writes empty maps of a schema as JSON objects', function () {
    $schema = \Dskripchenko\LaravelApi\Operations\Schema\JsonSchemas::encodable([
        'type' => 'object',
        'properties' => [],
        'required' => [],
        'items' => ['properties' => ['a' => ['type' => 'object', 'properties' => []]]],
        '$defs' => [],
    ]);

    expect(json_encode($schema))->toBe('{"type":"object","properties":{},"required":[],"items":{"properties":{"a":{"type":"object","properties":{}}}},"$defs":{}}');
});

it('describes an action without output fields with a valid result schema', function () {
    $document = (new \Dskripchenko\LaravelApi\Transports\JsonRpc\OpenRpcDocument(
        new \Dskripchenko\LaravelApi\Transports\NamedOperations(\Tests\Fixtures\OpenApi\ContextApi::class, 'v1', 'jsonrpc')
    ))->toArray();

    // A schema validator rejects `"properties": []`.
    expect(json_encode($document))->not->toContain('"properties":[]');
});
