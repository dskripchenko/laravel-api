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
