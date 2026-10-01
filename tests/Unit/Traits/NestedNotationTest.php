<?php

declare(strict_types=1);

use Tests\Fixtures\OpenApi\NestedApi;

function nestedSaveSchema(): array
{
    return NestedApi::getOpenApiConfig('v1')['paths']['/v1/nested/save']['post']
        ['requestBody']['content']['application/json']['schema'];
}

it('keeps the children of an array declared after them', function () {
    $widgets = nestedSaveSchema()['properties']['widgets'];

    expect($widgets['type'])->toBe('array');
    expect($widgets['description'])->toBe('Widgets, declared after their fields');
    expect(array_keys($widgets['items']['properties']))->toBe(['slug', 'size']);
});

it('marks required fields of nested objects', function () {
    $schema = nestedSaveSchema();

    expect($schema['properties']['widgets']['items']['required'])->toBe(['slug']);
    expect($schema['required'])->toBe(['key', 'widgets']);
});

it('describes the element of a list of scalars', function () {
    $abilities = nestedSaveSchema()['properties']['abilities'];

    expect($abilities['type'])->toBe('array');
    expect($abilities['items'])->toBe(['type' => 'string']);
    expect($abilities['description'])->toBe('Abilities');
});

it('keeps the flat siblings of nested output fields', function () {
    $schema = NestedApi::getOpenApiConfig('v1')['paths']['/v1/nested/show']['get']
        ['responses']['200']['content']['application/json']['schema'];

    expect(array_keys($schema['properties']))->toBe(['id', 'note', 'owner']);
    expect($schema['properties']['id']['type'])->toBe('integer');
    expect($schema['properties']['owner']['required'])->toBe(['name']);
    expect($schema['required'])->toBe(['id', 'owner']);
});
