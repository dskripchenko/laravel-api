<?php

declare(strict_types=1);

use Tests\Fixtures\Versions\v1\TestApi;
use Tests\Fixtures\Swagger\TemplateApi;
use Tests\Fixtures\Swagger\ExtendedApi;

it('generates valid openapi 3.0 structure', function () {
    $config = TestApi::getSwaggerApiConfig('v1');
    expect($config['openapi'])->toBe('3.0.0');
    expect($config['info'])->toHaveKeys(['title', 'description', 'version']);
    expect($config['servers'])->toBeArray();
    expect($config['servers'][0]['url'])->toContain('/api');
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
    $operation = $config['paths']['/v1/item/create']['post'];
    $contentType = array_key_first($operation['requestBody']['content']);
    $props = $operation['requestBody']['content'][$contentType]['schema']['properties'];

    expect($props)->toHaveKey('name');
});

it('parses @output tags correctly', function () {
    $config = TestApi::getSwaggerApiConfig('v1');
    $responseSchema = $config['paths']['/v1/item/show']['get']['responses']['200']['content']['application/json']['schema'];
    expect($responseSchema['properties'])->toHaveKey('id');
    expect($responseSchema['properties'])->toHaveKey('name');
});

it('includes components schemas with templates', function () {
    $config = TemplateApi::getSwaggerApiConfig('v1');
    expect($config['components']['schemas'])->toHaveKey('Error');
    expect($config['components']['schemas'])->toHaveKey('Success');
    expect($config['components']['schemas'])->toHaveKey('UserResponse');

    $userDef = $config['components']['schemas']['UserResponse'];
    expect($userDef['type'])->toBe('object');
    expect($userDef['properties'])->toHaveKey('id');
    expect($userDef['required'])->toContain('id');
    expect($userDef['required'])->toContain('name');
});

it('generates extended openapi with all new features', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');

    expect($config['openapi'])->toBe('3.0.0');
    expect($config['components'])->toHaveKey('securitySchemes');
    expect($config['components'])->toHaveKey('schemas');

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

    // Verify multi-response with content wrapper
    $responses = $config['paths']['/v1/extended/multiResponseAction']['get']['responses'];
    expect($responses)->toHaveKey('200');
    expect($responses)->toHaveKey('422');
    expect($responses['200']['content']['application/json']['schema']['$ref'])->toBe('#/components/schemas/UserResponse');
});

it('backward compat with simple api', function () {
    $config = TestApi::getSwaggerApiConfig('v1');

    expect($config['openapi'])->toBe('3.0.0');
    expect($config['paths'])->toHaveKey('/v1/item/list');

    // No components when no templates and no security
    if (isset($config['components'])) {
        expect($config['components'])->not->toHaveKey('securitySchemes');
    }

    // GET parameters still work with schema wrapper
    $params = $config['paths']['/v1/item/list']['get']['parameters'];
    $names = array_column($params, 'name');
    expect($names)->toContain('page');
    foreach ($params as $param) {
        expect($param)->toHaveKey('schema');
    }

    // Responses use 200 key with content wrapper
    $responseSchema = $config['paths']['/v1/item/show']['get']['responses']['200']['content']['application/json']['schema'];
    expect($responseSchema['properties'])->toHaveKey('id');

    // No consumes at operation level
    expect($config['paths']['/v1/item/list']['get'])->not->toHaveKey('consumes');
});
