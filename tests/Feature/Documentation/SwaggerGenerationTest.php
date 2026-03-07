<?php

declare(strict_types=1);

use Tests\Fixtures\Versions\v1\TestApi;
use Tests\Fixtures\Swagger\TemplateApi;

it('generates valid swagger 2.0 structure', function () {
    $config = TestApi::getSwaggerApiConfig('v1');
    expect($config['swagger'])->toBe('2.0');
    expect($config['info'])->toHaveKeys(['title', 'description', 'version']);
    expect($config['basePath'])->toBe('/api');
});

it('includes all action paths', function () {
    $config = TestApi::getSwaggerApiConfig('v1');
    $paths = array_keys($config['paths']);
    expect($paths)->toContain('/v1/item/list');
    expect($paths)->toContain('/v1/item/show');
    expect($paths)->toContain('/v1/item/create');
    expect($paths)->toContain('/v1/item/update');
    expect($paths)->toContain('/v1/item/remove');
    expect($paths)->toContain('/v1/open/ping');
});

it('parses @input tags correctly', function () {
    $config = TestApi::getSwaggerApiConfig('v1');
    $createParams = $config['paths']['/v1/item/create']['post']['parameters'];
    $names = array_column($createParams, 'name');

    // Should include auth middleware input + action inputs
    expect($names)->toContain('name');
});

it('parses @output tags correctly', function () {
    $config = TestApi::getSwaggerApiConfig('v1');
    $showResponse = $config['paths']['/v1/item/show']['get']['responses']['payload'];
    expect($showResponse['properties'])->toHaveKey('id');
    expect($showResponse['properties'])->toHaveKey('name');
});

it('includes definitions with templates', function () {
    $config = TemplateApi::getSwaggerApiConfig('v1');
    expect($config['definitions'])->toHaveKey('Error');
    expect($config['definitions'])->toHaveKey('Success');
    expect($config['definitions'])->toHaveKey('UserResponse');

    $userDef = $config['definitions']['UserResponse'];
    expect($userDef['type'])->toBe('object');
    expect($userDef['properties'])->toHaveKey('id');
    expect($userDef['required'])->toContain('id');
    expect($userDef['required'])->toContain('name');
});
