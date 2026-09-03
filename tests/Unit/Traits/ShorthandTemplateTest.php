<?php

declare(strict_types=1);

use Tests\Fixtures\OpenApi\ShorthandApi;

// === Shorthand string parsing ===

it('parses shorthand required type', function () {
    $config = ShorthandApi::getOpenApiConfig('v1');
    $schema = $config['components']['schemas']['OrderResponse'];

    expect($schema['properties']['id']['type'])->toBe('integer');
    expect($schema['properties']['status']['type'])->toBe('string');
    expect($schema['required'])->toContain('id');
    expect($schema['required'])->toContain('status');
});

it('parses shorthand optional type', function () {
    $config = ShorthandApi::getOpenApiConfig('v1');
    $schema = $config['components']['schemas']['OrderResponse'];

    expect($schema['properties']['total']['type'])->toBe('number');
    expect($schema['required'])->not->toContain('total');
});

it('parses shorthand type with format', function () {
    $config = ShorthandApi::getOpenApiConfig('v1');
    $schema = $config['components']['schemas']['OrderResponse'];

    expect($schema['properties']['created_at']['type'])->toBe('string');
    expect($schema['properties']['created_at']['format'])->toBe('date-time');
    expect($schema['required'])->not->toContain('created_at');
});

it('parses shorthand type with format and required', function () {
    $config = ShorthandApi::getOpenApiConfig('v1');
    $schema = $config['components']['schemas']['OrderResponse'];

    expect($schema['properties']['email']['type'])->toBe('string');
    expect($schema['properties']['email']['format'])->toBe('email');
    expect($schema['required'])->toContain('email');
});

it('mixes shorthand and array format in same template', function () {
    $config = ShorthandApi::getOpenApiConfig('v1');
    $schema = $config['components']['schemas']['MixedTemplate'];

    expect($schema['properties']['id']['type'])->toBe('integer');
    expect($schema['properties']['name']['type'])->toBe('string');
    expect($schema['properties']['score']['type'])->toBe('number');
    expect($schema['required'])->toContain('id');
    expect($schema['required'])->toContain('name');
    expect($schema['required'])->not->toContain('score');
});

it('resolves @ref in shorthand templates', function () {
    $config = ShorthandApi::getOpenApiConfig('v1');
    $schema = $config['components']['schemas']['OrderWithRef'];

    expect($schema['properties']['error']['$ref'])
        ->toBe('#/components/schemas/OrderError');

    expect($schema['properties']['items']['type'])->toBe('array');
    expect($schema['properties']['items']['items']['$ref'])
        ->toBe('#/components/schemas/OrderItem');
});

// === Integration: @response uses shorthand schemas ===

it('uses shorthand-defined schema in @response', function () {
    $config = ShorthandApi::getOpenApiConfig('v1');
    $responses = $config['paths']['/v1/order/create']['post']['responses'];

    $ref201 = $responses['201']['content']['application/json']['schema']['$ref'];
    $ref422 = $responses['422']['content']['application/json']['schema']['$ref'];

    expect($ref201)->toBe('#/components/schemas/OrderResponse');
    expect($ref422)->toBe('#/components/schemas/OrderError');
});

// === Backward compatibility: existing array format still works ===

it('existing array format templates still work', function () {
    $config = \Tests\Fixtures\OpenApi\ExtendedApi::getOpenApiConfig('v1');
    $schema = $config['components']['schemas']['UserResponse'];

    expect($schema['properties']['id']['type'])->toBe('integer');
    expect($schema['required'])->toContain('id');
    expect($schema['required'])->toContain('name');
});

// === Nested shorthand ===

it('unfolds a nested map into an object schema', function () {
    $config = ShorthandApi::getOpenApiConfig('v1');
    $payload = $config['components']['schemas']['NestedResult']['properties']['payload'];

    expect($payload['type'])->toBe('object');
    expect($payload['properties']['url'])->toBe(['type' => 'string', 'description' => 'Where to go next']);
    expect($payload['properties']['uuid'])->toBe(['type' => 'string', 'format' => 'uuid']);
    expect($payload['required'])->toBe(['uuid']);

    $client = $payload['properties']['client'];
    expect($client['type'])->toBe('object');
    expect($client['properties']['email'])->toBe(['type' => 'string', 'format' => 'email']);
    expect($client)->not->toHaveKey('required');
});

it('marks a field required through a key ending in !', function () {
    $config = ShorthandApi::getOpenApiConfig('v1');
    $schema = $config['components']['schemas']['NestedResult'];

    expect($schema['required'])->toContain('payload');
    expect($schema['required'])->toContain('lines');
    expect($schema['required'])->not->toContain('phones');
    expect($schema['properties'])->toHaveKey('payload');
    expect($schema['properties'])->not->toHaveKey('payload!');
});

it('reads a one-element list as an array', function () {
    $config = ShorthandApi::getOpenApiConfig('v1');
    $props = $config['components']['schemas']['NestedResult']['properties'];

    expect($props['phones'])->toBe(['type' => 'array', 'items' => ['type' => 'string']]);

    expect($props['lines']['type'])->toBe('array');
    expect($props['lines']['items']['type'])->toBe('object');
    expect($props['lines']['items']['properties']['sku'])->toBe(['type' => 'string']);
    expect($props['lines']['items']['required'])->toBe(['sku']);
});

it('accepts the required mark on a @ref', function () {
    $config = ShorthandApi::getOpenApiConfig('v1');
    $schema = $config['components']['schemas']['NestedResult'];

    expect($schema['properties']['error'])->toBe(['$ref' => '#/components/schemas/OrderError']);
    expect($schema['properties']['items']['items'])->toBe(['$ref' => '#/components/schemas/OrderItem']);
    expect($schema['required'])->toContain('error');
    expect($schema['required'])->toContain('items');
});

it('unfolds the shorthand inside a hand-written schema', function () {
    $config = ShorthandApi::getOpenApiConfig('v1');
    $schema = $config['components']['schemas']['NestedResult'];
    $explicit = $schema['properties']['explicit'];

    expect($schema['required'])->toContain('explicit');
    expect($explicit['properties']['code'])->toBe(['type' => 'integer']);
    expect($explicit['required'])->toBe(['code']);
    expect($explicit['properties']['tags'])->toBe(['type' => 'array', 'items' => ['type' => 'string']]);
    expect($explicit['properties']['ref'])->toBe(['$ref' => '#/components/schemas/OrderError']);
});

it('leaves an @ in a description alone', function () {
    // The old recursive walk turned any string with an @ into a $ref — a
    // description mentioning @support became a reference to nothing.
    $config = ShorthandApi::getOpenApiConfig('v1');
    $note = $config['components']['schemas']['NestedResult']['properties']['note'];

    expect($note)->toBe(['type' => 'string', 'description' => 'Contact @support if unsure']);
});
