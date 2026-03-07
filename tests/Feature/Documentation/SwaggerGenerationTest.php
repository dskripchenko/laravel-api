<?php

declare(strict_types=1);

use Tests\Fixtures\Versions\v1\TestApi;
use Tests\Fixtures\Swagger\TemplateApi;
use Tests\Fixtures\Swagger\ExtendedApi;

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

it('generates extended swagger with all new features', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');

    expect($config['swagger'])->toBe('2.0');
    expect($config)->toHaveKey('securityDefinitions');
    expect($config)->toHaveKey('definitions');

    // Check that extended paths exist
    $paths = array_keys($config['paths']);
    expect($paths)->toContain('/v1/extended/headerAction');
    expect($paths)->toContain('/v1/extended/securityAction');
    expect($paths)->toContain('/v1/extended/deprecatedAction');
    expect($paths)->toContain('/v1/extended/multiResponseAction');
    expect($paths)->toContain('/v1/extended/nestedInputAction');
    expect($paths)->toContain('/v1/extended/formatAction');
    expect($paths)->toContain('/v1/extended/enumAction');
    expect($paths)->toContain('/v1/extended/modelRefAction');
    expect($paths)->toContain('/v1/extended/defaultExampleAction');
    expect($paths)->toContain('/v1/extended/fileUploadAction');

    // Verify deprecated
    expect($config['paths']['/v1/extended/deprecatedAction']['post']['deprecated'])->toBeTrue();

    // Verify operationId
    expect($config['paths']['/v1/extended/formatAction']['post']['operationId'])->toBe('extended_formatAction');

    // Verify security
    expect($config['paths']['/v1/extended/securityAction']['post']['security'])->toBe([['BearerAuth' => []]]);

    // Verify multi-response
    $responses = $config['paths']['/v1/extended/multiResponseAction']['get']['responses'];
    expect($responses)->toHaveKey('200');
    expect($responses)->toHaveKey('422');
});

it('backward compat with simple api', function () {
    $config = TestApi::getSwaggerApiConfig('v1');

    // Same structure as before
    expect($config['swagger'])->toBe('2.0');
    expect($config['paths'])->toHaveKey('/v1/item/list');
    expect($config)->not->toHaveKey('securityDefinitions');

    // Parameters still work
    $params = $config['paths']['/v1/item/list']['get']['parameters'];
    $names = array_column($params, 'name');
    expect($names)->toContain('page');

    // Responses still work
    $response = $config['paths']['/v1/item/show']['get']['responses']['payload'];
    expect($response['properties'])->toHaveKey('id');

    // Consumes defaults to urlencoded
    expect($config['paths']['/v1/item/list']['get']['consumes'])->toBe(['application/x-www-form-urlencoded']);
});
