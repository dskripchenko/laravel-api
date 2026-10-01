<?php

declare(strict_types=1);

use Tests\Fixtures\OpenApi\ContextApi;

function contextSpec(): array
{
    return ContextApi::getOpenApiConfig('v1');
}

function jsonBody(array $operation): array
{
    return $operation['requestBody']['content']['application/json']['schema'];
}

it('describes each controller key with its own fields through one method', function () {
    $paths = contextSpec()['paths'];

    $users = jsonBody($paths['/v1/users/create']['post']);
    $posts = jsonBody($paths['/v1/posts/create']['post']);

    expect(array_keys($users['properties']))->toBe(['email', 'age']);
    expect(array_keys($posts['properties']))->toBe(['title', 'status']);
});

it('passes a returned schema through, constraints included', function () {
    $paths = contextSpec()['paths'];

    $users = jsonBody($paths['/v1/users/create']['post']);
    expect($users['type'])->toBe('object');
    expect($users['properties']['email'])->toBe(['type' => 'string', 'format' => 'email', 'maxLength' => 255]);
    expect($users['properties']['age']['minimum'])->toBe(0);
    expect($users['required'])->toBe(['email']);

    $posts = jsonBody($paths['/v1/posts/create']['post']);
    expect($posts['properties']['status']['enum'])->toBe(['draft', 'published']);
});

it('tells the method which action it describes', function () {
    $paths = contextSpec()['paths'];

    // The fixture marks fields required on create only.
    expect(jsonBody($paths['/v1/users/create']['post']))->toHaveKey('required');
    expect(jsonBody($paths['/v1/users/update']['post'])['required'] ?? [])->toBe(['id']);
});

it('merges docblock lines with the returned schema', function () {
    $schema = jsonBody(contextSpec()['paths']['/v1/posts/update']['post']);

    expect(array_keys($schema['properties']))->toBe(['id', 'title', 'status']);
    expect($schema['properties']['id']['type'])->toBe('integer');
    expect($schema['properties']['id']['description'])->toBe('Identifier');
    expect($schema['required'])->toBe(['id']);
});

it('turns a returned schema into query parameters for GET', function () {
    $operation = contextSpec()['paths']['/v1/posts/search']['get'];

    expect($operation)->not->toHaveKey('requestBody');
    $parameters = collect($operation['parameters'])->keyBy('name');

    expect($parameters->keys()->all())->toBe(['page', 'title', 'status']);
    expect($parameters['page']['in'])->toBe('query');
    expect($parameters['page']['required'])->toBeFalse();
    expect($parameters['status']['schema'])->toBe(['type' => 'string', 'enum' => ['draft', 'published']]);
});

it('hands the context to @output [method] as well', function () {
    $schema = contextSpec()['paths']['/v1/users/create']['post']['responses']['200']['content']['application/json']['schema'];

    expect(array_keys($schema['properties']))->toBe(['id', 'email', 'age']);
    expect($schema['required'])->toBe(['id']);
    expect($schema['x-tag'])->toBe('output');
});

it('mixes @output lines with lines a method returned for the key', function () {
    $schema = contextSpec()['paths']['/v1/posts/show']['get']['responses']['200']['content']['application/json']['schema'];

    expect(array_keys($schema['properties']))->toBe(['id', 'title', 'status']);
    expect($schema['properties']['title']['description'])->toBe('From posts');
});

it('keeps calling a method that asks for no context exactly as before', function () {
    $body = contextSpec()['paths']['/v1/users/legacy']['post']['requestBody'];
    $schema = $body['content']['application/x-www-form-urlencoded']['schema'];

    expect(array_keys($schema['properties']))->toBe(['name', 'count']);
    expect($schema['required'])->toBe(['name']);
});

it('binds the scalars by name and the object by type, leaving other parameters alone', function () {
    $body = contextSpec()['paths']['/v1/posts/scalars']['post']['requestBody'];
    $marker = $body['content']['application/x-www-form-urlencoded']['schema']['properties']['marker'];

    expect($marker['description'])->toBe('v1|posts|scalars|post|spicy|default|scalars');
});
