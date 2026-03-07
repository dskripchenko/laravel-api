<?php

declare(strict_types=1);

use Tests\Fixtures\Versions\v1\TestApi as V1Api;
use Tests\Fixtures\Swagger\TemplateApi;
use Dskripchenko\LaravelApi\Components\BaseApi;

it('generates swagger 2.0 config', function () {
    $config = V1Api::getSwaggerApiConfig('v1');
    expect($config['swagger'])->toBe('2.0');
});

it('includes info from class docblock', function () {
    $config = V1Api::getSwaggerApiConfig('v1');
    expect($config['info']['title'])->toBe('Test API v1');
    expect($config['info']['version'])->toBe('v1');
});

it('includes host and basePath', function () {
    $config = V1Api::getSwaggerApiConfig('v1');
    expect($config['basePath'])->toBe('/api');
    expect($config)->toHaveKey('host');
    expect($config)->toHaveKey('schemes');
});

it('generates paths for actions', function () {
    $config = V1Api::getSwaggerApiConfig('v1');
    expect($config['paths'])->not->toBeEmpty();
    // Should have paths like /v1/item/list, /v1/item/show, etc.
    expect($config['paths'])->toHaveKey('/v1/item/list');
    expect($config['paths'])->toHaveKey('/v1/item/show');
});

it('does not include disabled actions in paths', function () {
    $config = V1Api::getSwaggerApiConfig('v1');
    expect($config['paths'])->not->toHaveKey('/v1/item/disabled');
});

it('does not include definitions when useResponseTemplates is false', function () {
    $config = V1Api::getSwaggerApiConfig('v1');
    expect($config)->not->toHaveKey('definitions');
});

it('includes definitions when useResponseTemplates is true', function () {
    $config = TemplateApi::getSwaggerApiConfig('v1');
    expect($config)->toHaveKey('definitions');
    expect($config['definitions'])->toHaveKey('Error');
    expect($config['definitions'])->toHaveKey('Success');
    expect($config['definitions'])->toHaveKey('UserResponse');
});

it('parses @input tags into parameters', function () {
    $config = V1Api::getSwaggerApiConfig('v1');
    $listPath = $config['paths']['/v1/item/list'];
    $getParams = $listPath['get']['parameters'];
    // Should have page and perPage inputs
    $names = array_column($getParams, 'name');
    expect($names)->toContain('page');
    expect($names)->toContain('perPage');
});

it('sets parameter in to query for GET methods', function () {
    $config = V1Api::getSwaggerApiConfig('v1');
    $params = $config['paths']['/v1/item/list']['get']['parameters'];
    foreach ($params as $param) {
        expect($param['in'])->toBe('query');
    }
});

it('sets parameter in to formData for POST methods', function () {
    $config = V1Api::getSwaggerApiConfig('v1');
    $params = $config['paths']['/v1/item/create']['post']['parameters'];
    foreach ($params as $param) {
        expect($param['in'])->toBe('formData');
    }
});

it('parses @output tags into response', function () {
    $config = V1Api::getSwaggerApiConfig('v1');
    $showPath = $config['paths']['/v1/item/show'];
    $response = $showPath['get']['responses']['payload'];
    expect($response['properties'])->toHaveKey('id');
    expect($response['properties'])->toHaveKey('name');
});

it('uses template reference in output when useResponseTemplates is true', function () {
    $config = TemplateApi::getSwaggerApiConfig('v1');
    $getUserPath = $config['paths']['/v1/template/getUser'];
    $response = $getUserPath['get']['responses']['payload'];
    expect($response['schema']['$ref'])->toBe('#/definitions/UserResponse');
});

it('falls back unknown type to string', function () {
    // Testing getSafeDataType via reflection
    $ref = new \ReflectionMethod(BaseApi::class, 'getSafeDataType');
    $ref->setAccessible(true);

    // Use a concrete subclass since it's a trait method
    expect($ref->invoke(null, 'unknown_type'))->toBe('string');
    expect($ref->invoke(null, 'integer'))->toBe('integer');
    expect($ref->invoke(null, 'boolean'))->toBe('boolean');
});
