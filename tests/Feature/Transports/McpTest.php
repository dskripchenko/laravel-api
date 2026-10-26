<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApi\Facades\ApiModule;
use Dskripchenko\LaravelApi\Providers\ApiServiceProvider;
use Dskripchenko\LaravelApi\Transports\Mcp\McpServer;
use Dskripchenko\LaravelApi\Transports\Mcp\McpToolset;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/** Turns the MCP endpoint on — the routes were registered at boot, while it was off. */
function enableMcp(array $config = []): void
{
    config(['laravel-api.mcp' => array_merge(config('laravel-api.mcp'), ['enabled' => true], $config)]);
    Route::prefix(ApiModule::getApiPrefix())->group(static fn () => ApiServiceProvider::registerMcpRoutes());
    Route::getRoutes()->refreshNameLookups();
}

function mcp(object $test, string $method, array $params = [], array $headers = [], string $version = 'v1', int|string|null $id = 1): TestResponse
{
    $message = ['jsonrpc' => '2.0', 'method' => $method, 'params' => $params];
    if ($id !== null) {
        $message['id'] = $id;
    }

    return $test->postJson("/api/mcp/{$version}", $message, $headers);
}

function toolNames(TestResponse $response): array
{
    return array_column($response->json('result.tools'), 'name');
}

it('is off unless enabled', function () {
    $this->postJson('/api/mcp/v1', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])->assertNotFound();
});

it('initializes, agreeing on a protocol version it knows', function () {
    enableMcp(['name' => 'Shop']);

    $response = mcp($this, 'initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => [], 'clientInfo' => ['name' => 't', 'version' => '1']]);

    $response->assertOk()->assertJson([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => ['name' => 'Shop', 'version' => 'v1'],
            'instructions' => "Test API v1\n\nTest API description",
        ],
    ]);

    expect(mcp($this, 'initialize', ['protocolVersion' => '1999-01-01'])->json('result.protocolVersion'))
        ->toBe(McpServer::PROTOCOL_VERSIONS[0]);
});

it('accepts notifications without answering them', function () {
    enableMcp();

    $response = mcp($this, 'notifications/initialized', id: null);

    $response->assertStatus(202);
    expect($response->getContent())->toBe('');
});

it('answers ping with an empty object', function () {
    enableMcp();

    expect(mcp($this, 'ping')->getContent())->toBe('{"jsonrpc":"2.0","id":1,"result":{}}');
});

it('lists every action as a tool', function () {
    enableMcp();

    $response = mcp($this, 'tools/list');

    expect(toolNames($response))->toEqualCanonicalizing(['item.list', 'item.show', 'item.create', 'item.update', 'item.remove', 'open.ping']);

    $ping = collect($response->json('result.tools'))->firstWhere('name', 'open.ping');
    expect($ping['annotations'])->toBe(['readOnlyHint' => true]);
    // The first line of the summary: a docblock's second line runs into it.
    expect($ping['title'])->toBe('Ping');
    expect($ping['inputSchema']['type'])->toBe('object');

    // The input of a middleware travels with the action's own.
    $create = collect($response->json('result.tools'))->firstWhere('name', 'item.create');
    expect(array_keys($create['inputSchema']['properties']))->toContain('name', 'token');
    expect($create)->not->toHaveKey('annotations');
});

it('calls a tool and hands back what the action answered', function () {
    enableMcp();

    mcp($this, 'tools/call', ['name' => 'open.ping', 'arguments' => []])
        ->assertOk()
        ->assertExactJson([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'content' => [['type' => 'text', 'text' => '{"pong":true}']],
                'isError' => false,
                'structuredContent' => ['pong' => true],
            ],
        ]);
});

it('calls as the caller of the MCP request, through the same middleware', function () {
    enableMcp();

    $refused = mcp($this, 'tools/call', ['name' => 'item.list']);
    expect($refused->json('result.isError'))->toBeTrue();
    expect($refused->json('result.structuredContent.errorKey'))->toBe('auth_error');

    $allowed = mcp($this, 'tools/call', ['name' => 'item.create', 'arguments' => ['name' => 'New']], ['X-Auth-Token' => 'token']);
    expect($allowed->json('result.isError'))->toBeFalse();
    expect($allowed->json('result.structuredContent'))->toBe(['id' => 1, 'name' => 'New']);
});

it('answers protocol mistakes with JSON-RPC errors', function () {
    enableMcp();

    expect(mcp($this, 'tools/call', ['name' => 'item.nope'])->json('error.code'))->toBe(McpServer::INVALID_PARAMS);
    expect(mcp($this, 'resources/list')->json('error.code'))->toBe(McpServer::METHOD_NOT_FOUND);

    $batch = $this->postJson('/api/mcp/v1', [['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']]);
    expect($batch->json('error.code'))->toBe(McpServer::INVALID_REQUEST);

    $parse = $this->call('POST', '/api/mcp/v1', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{nope');
    $parse->assertStatus(400);
    expect($parse->json('error.code'))->toBe(McpServer::PARSE_ERROR);
});

it('keeps no sessions and streams nothing', function () {
    enableMcp();

    $this->get('/api/mcp/v1')->assertStatus(405)->assertHeader('Allow', 'POST');
    $this->delete('/api/mcp/v1')->assertStatus(405);
});

it('serves the versions it is told to', function () {
    enableMcp(['versions' => ['v2']]);

    mcp($this, 'ping')->assertNotFound();
    mcp($this, 'ping', version: 'v2')->assertOk();
    mcp($this, 'ping', version: 'v9')->assertNotFound();
});

it('refuses a browser origin it does not know', function () {
    enableMcp();

    mcp($this, 'ping', [], ['Origin' => 'https://evil.example'])->assertForbidden();
    mcp($this, 'ping', [], ['Origin' => 'http://localhost'])->assertOk();

    enableMcp(['allowed_origins' => ['https://agent.example']]);
    mcp($this, 'ping', [], ['Origin' => 'https://agent.example'])->assertOk();
});

// --- The toolset on its own ---------------------------------------------------

class McpToolsetApi extends BaseApi
{
    public static $useResponseTemplates = true;

    public static function getMethods(): array
    {
        return ['controllers' => [
            'order' => ['controller' => McpToolsetController::class, 'actions' => [
                'place' => ['method' => ['post']],
                'internal' => ['mcp' => false],
            ]],
            'admin' => ['controller' => McpToolsetController::class, 'mcp' => false, 'actions' => ['place']],
        ]];
    }

    public static function getSchemaTemplates(): array
    {
        return [
            'Order' => ['item!' => '@Item', 'note' => 'string'],
            'Item' => ['id' => 'integer!'],
            'Unused' => ['x' => 'string'],
        ];
    }
}

class McpToolsetController
{
    /**
     * Place an order
     *
     * The order goes to the warehouse.
     * @input @Order
     * @deprecated
     */
    public function place()
    {
    }

    /**
     * Internal
     */
    public function internal()
    {
    }
}

it('leaves out what getMethods() marks with mcp => false', function () {
    $tools = (new McpToolset(McpToolsetApi::class, 'v1'))->tools();

    expect(array_column($tools, 'name'))->toBe(['order.place']);
});

it('carries the templates an input refers to under $defs', function () {
    [$tool] = (new McpToolset(McpToolsetApi::class, 'v1'))->tools();

    expect($tool['title'])->toBe('Place an order');
    expect($tool['description'])->toBe("Deprecated. Place an order\n\nThe order goes to the warehouse.");
    expect($tool['inputSchema'])->toBe([
        'type' => 'object',
        'allOf' => [['$ref' => '#/$defs/Order']],
        '$defs' => [
            'Item' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']],
            'Order' => [
                'type' => 'object',
                'properties' => ['item' => ['$ref' => '#/$defs/Item'], 'note' => ['type' => 'string']],
                'required' => ['item'],
            ],
        ],
    ]);
});

// --- api:cache ------------------------------------------------------------------

it('caches the tools with the map, and lists them from there', function () {
    enableMcp();
    try {
        $this->artisan('api:cache', ['--no-docs' => true])
            ->expectsOutputToContain('MCP tools cached for v1 (6), v2')
            ->assertSuccessful();

        $cached = \Dskripchenko\LaravelApi\Services\ApiCache::mcpTools(\Tests\Fixtures\Versions\v1\TestApi::class, 'v1');
        expect(array_column($cached, 'name'))->toContain('open.ping');

        // An empty input stays a JSON object through var_export().
        expect(mcp($this, 'tools/list')->getContent())
            ->toContain('"name":"open.ping","title":"Ping","description":"Ping\\nHealth check endpoint.');
        expect(mcp($this, 'tools/list')->getContent())->toContain('"inputSchema":{"type":"object","properties":{}}');

        // Another class with the same version string is not served from it.
        expect(\Dskripchenko\LaravelApi\Services\ApiCache::mcpTools(McpToolsetApi::class, 'v1'))->toBeNull();
    } finally {
        \Dskripchenko\LaravelApi\Services\ApiCache::clear();
    }
});

it('serves tools/list from the cache rather than describing the actions again', function () {
    enableMcp();
    $data = \Dskripchenko\LaravelApi\Services\ApiCache::build(app('api_module'));
    $data['mcp']['v1']['tools'] = [['name' => 'frozen.tool', 'title' => 'Frozen', 'description' => '', 'inputSchema' => ['type' => 'object', 'properties' => new stdClass()]]];
    \Dskripchenko\LaravelApi\Services\ApiCache::write($data);
    try {
        expect(toolNames(mcp($this, 'tools/list')))->toBe(['frozen.tool']);
        // A call still finds its action through the route map.
        expect(mcp($this, 'tools/call', ['name' => 'open.ping'])->json('result.isError'))->toBeFalse();
    } finally {
        \Dskripchenko\LaravelApi\Services\ApiCache::clear();
    }
});
