<?php

declare(strict_types=1);

use Tests\Fixtures\Versions\v1\TestApi as V1Api;
use Tests\Fixtures\OpenApi\TemplateApi;
use Tests\Fixtures\OpenApi\ExtendedApi;
use Dskripchenko\LaravelApi\Components\BaseApi;

it('generates openapi 3.0 config', function () {
    $config = V1Api::getOpenApiConfig('v1');
    expect($config['openapi'])->toBe('3.0.0');
});

it('includes info from class docblock', function () {
    $config = V1Api::getOpenApiConfig('v1');
    expect($config['info']['title'])->toBe('Test API v1');
    expect($config['info']['version'])->toBe('v1');
});

it('includes servers instead of host and basePath', function () {
    $config = V1Api::getOpenApiConfig('v1');
    expect($config['servers'])->toBeArray();
    expect($config['servers'][0])->toHaveKey('url');
    expect($config['servers'][0]['url'])->toContain('/api');
    expect($config)->not->toHaveKey('host');
    expect($config)->not->toHaveKey('basePath');
    expect($config)->not->toHaveKey('schemes');
});

it('generates paths for actions', function () {
    $config = V1Api::getOpenApiConfig('v1');
    expect($config['paths'])->not->toBeEmpty();
    expect($config['paths'])->toHaveKey('/v1/item/list');
    expect($config['paths'])->toHaveKey('/v1/item/show');
});

it('does not include disabled actions in paths', function () {
    $config = V1Api::getOpenApiConfig('v1');
    expect($config['paths'])->not->toHaveKey('/v1/item/disabled');
});

it('does not include components schemas when useResponseTemplates is false', function () {
    $config = V1Api::getOpenApiConfig('v1');
    if (isset($config['components'])) {
        expect($config['components'])->not->toHaveKey('schemas');
    } else {
        expect($config)->not->toHaveKey('components');
    }
});

it('includes components schemas when useResponseTemplates is true', function () {
    $config = TemplateApi::getOpenApiConfig('v1');
    expect($config['components']['schemas'])->toHaveKey('Error');
    expect($config['components']['schemas'])->toHaveKey('Success');
    expect($config['components']['schemas'])->toHaveKey('UserResponse');
});

it('parses @input tags into parameters', function () {
    $config = V1Api::getOpenApiConfig('v1');
    $listPath = $config['paths']['/v1/item/list'];
    $getParams = $listPath['get']['parameters'];
    $names = array_column($getParams, 'name');
    expect($names)->toContain('page');
    expect($names)->toContain('perPage');
});

it('sets parameter in to query for GET methods', function () {
    $config = V1Api::getOpenApiConfig('v1');
    $params = $config['paths']['/v1/item/list']['get']['parameters'];
    foreach ($params as $param) {
        expect($param['in'])->toBe('query');
        expect($param)->toHaveKey('schema');
        expect($param['schema'])->toHaveKey('type');
    }
});

it('moves POST formData params to requestBody', function () {
    $config = V1Api::getOpenApiConfig('v1');
    $operation = $config['paths']['/v1/item/create']['post'];
    expect($operation)->toHaveKey('requestBody');
    $contentType = array_key_first($operation['requestBody']['content']);
    $schema = $operation['requestBody']['content'][$contentType]['schema'];
    expect($schema['properties'])->toHaveKey('name');
});

it('parses @output tags into response', function () {
    $config = V1Api::getOpenApiConfig('v1');
    $showPath = $config['paths']['/v1/item/show'];
    $responseSchema = $showPath['get']['responses']['200']['content']['application/json']['schema'];
    expect($responseSchema['properties'])->toHaveKey('id');
    expect($responseSchema['properties'])->toHaveKey('name');
});

it('uses template reference in output when useResponseTemplates is true', function () {
    $config = TemplateApi::getOpenApiConfig('v1');
    $getUserPath = $config['paths']['/v1/template/getUser'];
    $responseSchema = $getUserPath['get']['responses']['200']['content']['application/json']['schema'];
    expect($responseSchema['$ref'])->toBe('#/components/schemas/UserResponse');
});

it('falls back unknown type to string', function () {
    $ref = new \ReflectionMethod(BaseApi::class, 'getSafeDataType');
    $ref->setAccessible(true);

    expect($ref->invoke(null, 'unknown_type'))->toBe('string');
    expect($ref->invoke(null, 'integer'))->toBe('integer');
    expect($ref->invoke(null, 'boolean'))->toBe('boolean');
});

// === Phase 1: Format, Enum, operationId, deprecated ===

it('parses format from type parentheses for input', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $operation = $config['paths']['/v1/extended/formatAction']['post'];
    $requestBody = $operation['requestBody']['content'];
    $contentType = array_key_first($requestBody);
    $props = $requestBody[$contentType]['schema']['properties'];

    expect($props['email']['type'])->toBe('string');
    expect($props['email']['format'])->toBe('email');
    expect($props['bigId']['type'])->toBe('integer');
    expect($props['bigId']['format'])->toBe('int64');
});

it('parses format for output', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $responseSchema = $config['paths']['/v1/extended/formatAction']['post']['responses']['200']['content']['application/json']['schema'];
    $props = $responseSchema['properties'];

    expect($props['createdAt']['format'])->toBe('date-time');
    expect($props['count']['format'])->toBe('int32');
});

it('ignores format when no parentheses', function () {
    $config = V1Api::getOpenApiConfig('v1');
    $params = $config['paths']['/v1/item/list']['get']['parameters'];
    $pageParam = collect($params)->firstWhere('name', 'page');

    expect($pageParam['schema'])->not->toHaveKey('format');
});

it('extracts enum from description brackets', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $operation = $config['paths']['/v1/extended/enumAction']['post'];
    $props = $operation['requestBody']['content']['application/x-www-form-urlencoded']['schema']['properties'];

    expect($props['status']['enum'])->toBe(['active', 'blocked', 'pending']);
});

it('strips enum brackets from description', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $operation = $config['paths']['/v1/extended/enumAction']['post'];
    $props = $operation['requestBody']['content']['application/x-www-form-urlencoded']['schema']['properties'];

    expect($props['status']['description'])->toBe('Status');
    expect($props['status']['description'])->not->toContain('[');
});

it('generates operationId', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $method = $config['paths']['/v1/extended/formatAction']['post'];

    expect($method['operationId'])->toBe('extended_formatAction');
});

it('sets deprecated flag', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $method = $config['paths']['/v1/extended/deprecatedAction']['post'];

    expect($method['deprecated'])->toBeTrue();
});

it('no deprecated when absent', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $method = $config['paths']['/v1/extended/formatAction']['post'];

    expect($method)->not->toHaveKey('deprecated');
});

// === Phase 2: Headers, Responses, Security ===

it('parses @header into header parameters', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $params = $config['paths']['/v1/extended/headerAction']['post']['parameters'];
    $authParam = collect($params)->firstWhere('name', 'Authorization');
    $reqIdParam = collect($params)->firstWhere('name', 'X-Request-Id');

    expect($authParam['in'])->toBe('header');
    expect($authParam['required'])->toBeTrue();
    expect($authParam['schema']['type'])->toBe('string');
    expect($reqIdParam['in'])->toBe('header');
    expect($reqIdParam['required'])->toBeFalse();
});

it('aggregates @header from middleware', function () {
    $apiClass = new class extends BaseApi {
        public static $useResponseTemplates = false;

        public static function getMethods(): array
        {
            return [
                'controllers' => [
                    'extended' => [
                        'controller' => \Tests\Fixtures\OpenApi\ExtendedController::class,
                        'middleware' => [\Tests\Fixtures\OpenApi\HeaderMiddleware::class],
                        'actions' => [
                            'headerAction' => [
                                'action' => 'headerAction',
                                'method' => 'post',
                            ],
                        ],
                    ],
                ],
            ];
        }
    };

    $config = $apiClass::getOpenApiConfig('v1');
    $params = $config['paths']['/v1/extended/headerAction']['post']['parameters'];
    $headerParams = collect($params)->where('in', 'header');

    $names = $headerParams->pluck('name')->toArray();
    expect($names)->toContain('X-Auth-Token');
    expect($names)->toContain('Authorization');
});

it('parses @response with HTTP codes', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $responses = $config['paths']['/v1/extended/multiResponseAction']['get']['responses'];

    expect($responses)->toHaveKey('200');
    expect($responses)->toHaveKey('422');
});

it('uses template ref in @response with content wrapper', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $responses = $config['paths']['/v1/extended/multiResponseAction']['get']['responses'];

    $ref200 = $responses['200']['content']['application/json']['schema']['$ref'];
    $ref422 = $responses['422']['content']['application/json']['schema']['$ref'];
    expect($ref200)->toBe('#/components/schemas/UserResponse');
    expect($ref422)->toBe('#/components/schemas/ValidationError');
});

it('falls back to @output when no @response', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $responses = $config['paths']['/v1/extended/formatAction']['post']['responses'];

    expect($responses)->toHaveKey('200');
    $schema = $responses['200']['content']['application/json']['schema'];
    expect($schema['properties'])->toHaveKey('createdAt');
});

it('includes components securitySchemes', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');

    expect($config['components'])->toHaveKey('securitySchemes');
    expect($config['components']['securitySchemes'])->toHaveKey('BearerAuth');
    expect($config['components']['securitySchemes']['BearerAuth']['type'])->toBe('apiKey');
});

it('parses @security tag', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $method = $config['paths']['/v1/extended/securityAction']['post'];

    expect($method['security'])->toBe([['BearerAuth' => []]]);
});

it('no components securitySchemes when empty', function () {
    $config = V1Api::getOpenApiConfig('v1');

    if (isset($config['components'])) {
        expect($config['components'])->not->toHaveKey('securitySchemes');
    } else {
        expect($config)->not->toHaveKey('components');
    }
});

// === Phase 3: Nested, Auto-consumes ===

it('dot-notation inputs to requestBody with nested schema', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $operation = $config['paths']['/v1/extended/nestedInputAction']['post'];
    $requestBody = $operation['requestBody'];
    $schema = $requestBody['content']['application/json']['schema'];

    expect($schema['properties'])->toHaveKey('address');
    expect($schema['properties']['address']['type'])->toBe('object');
    expect($schema['properties']['address']['properties'])->toHaveKey('city');
});

it('array notation in dot-notation', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $operation = $config['paths']['/v1/extended/nestedInputAction']['post'];
    $schema = $operation['requestBody']['content']['application/json']['schema'];

    $tags = $schema['properties']['tags'];
    expect($tags['type'])->toBe('array');
    expect($tags['items']['type'])->toBe('object');
    expect($tags['items']['properties'])->toHaveKey('id');
    expect($tags['items']['properties'])->toHaveKey('name');
});

it('flat POST inputs go to requestBody', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $operation = $config['paths']['/v1/extended/enumAction']['post'];

    expect($operation)->toHaveKey('requestBody');
    $contentType = array_key_first($operation['requestBody']['content']);
    expect($contentType)->toBe('application/x-www-form-urlencoded');
});

it('dot-notation outputs nested', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $responseSchema = $config['paths']['/v1/extended/nestedInputAction']['post']['responses']['200']['content']['application/json']['schema'];

    expect($responseSchema['properties'])->toHaveKey('address');
    expect($responseSchema['properties']['address']['type'])->toBe('object');
    expect($responseSchema['properties']['address']['properties'])->toHaveKey('city');
});

it('requestBody uses multipart for file', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $operation = $config['paths']['/v1/extended/fileUploadAction']['post'];

    expect($operation['requestBody']['content'])->toHaveKey('multipart/form-data');
    $props = $operation['requestBody']['content']['multipart/form-data']['schema']['properties'];
    expect($props['avatar']['type'])->toBe('string');
    expect($props['avatar']['format'])->toBe('binary');
});

it('requestBody uses json for nested body', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $operation = $config['paths']['/v1/extended/nestedInputAction']['post'];

    expect($operation['requestBody']['content'])->toHaveKey('application/json');
});

it('requestBody uses urlencoded for flat formData', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $operation = $config['paths']['/v1/extended/enumAction']['post'];

    expect($operation['requestBody']['content'])->toHaveKey('application/x-www-form-urlencoded');
});

// === Phase 4: Model/Ref ===

it('requestBody $ref for @input @Model', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $operation = $config['paths']['/v1/extended/modelRefAction']['post'];
    $schema = $operation['requestBody']['content']['application/json']['schema'];

    expect($schema['$ref'])->toBe('#/components/schemas/OrderCreateRequest');
});

it('output field $ref', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $responseSchema = $config['paths']['/v1/extended/modelRefAction']['post']['responses']['200']['content']['application/json']['schema'];

    expect($responseSchema['properties']['user']['$ref'])->toBe('#/components/schemas/User');
});

it('output array $ref', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $responseSchema = $config['paths']['/v1/extended/modelRefAction']['post']['responses']['200']['content']['application/json']['schema'];

    expect($responseSchema['properties']['users']['type'])->toBe('array');
    expect($responseSchema['properties']['users']['items']['$ref'])->toBe('#/components/schemas/User');
});

// === Phase 5: Default/Example ===

it('default from @default tag', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $params = $config['paths']['/v1/extended/defaultExampleAction']['get']['parameters'];
    $pageParam = collect($params)->firstWhere('name', 'page');

    expect($pageParam['schema']['default'])->toBe(1);
});

it('example from @example tag', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $params = $config['paths']['/v1/extended/defaultExampleAction']['get']['parameters'];
    $pageParam = collect($params)->firstWhere('name', 'page');

    expect($pageParam['example'])->toBe(3);
});

// === Phase 6: Optional @output ===

it('marks required @output fields in required array', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $responseSchema = $config['paths']['/v1/extended/optionalOutputAction']['get']['responses']['200']['content']['application/json']['schema'];

    expect($responseSchema['required'])->toContain('id');
    expect($responseSchema['required'])->toContain('name');
    expect($responseSchema['required'])->not->toContain('email');
    expect($responseSchema['required'])->not->toContain('phone');
});

it('includes optional @output fields in properties', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $responseSchema = $config['paths']['/v1/extended/optionalOutputAction']['get']['responses']['200']['content']['application/json']['schema'];

    expect($responseSchema['properties'])->toHaveKey('id');
    expect($responseSchema['properties'])->toHaveKey('name');
    expect($responseSchema['properties'])->toHaveKey('email');
    expect($responseSchema['properties'])->toHaveKey('phone');
});

it('does not leak required flag into individual output properties', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $responseSchema = $config['paths']['/v1/extended/optionalOutputAction']['get']['responses']['200']['content']['application/json']['schema'];

    foreach ($responseSchema['properties'] as $prop) {
        expect($prop)->not->toHaveKey('required');
    }
});

it('omits required array when all @output fields are optional', function () {
    $apiClass = new class extends BaseApi {
        public static $useResponseTemplates = false;

        public static function getMethods(): array
        {
            return [
                'controllers' => [
                    'test' => [
                        'controller' => \Tests\Fixtures\OpenApi\AllOptionalOutputController::class,
                        'actions' => [
                            'allOptional' => [
                                'action' => 'allOptional',
                                'method' => 'get',
                            ],
                        ],
                    ],
                ],
            ];
        }
    };

    $config = $apiClass::getOpenApiConfig('v1');
    $responseSchema = $config['paths']['/v1/test/allOptional']['get']['responses']['200']['content']['application/json']['schema'];

    expect($responseSchema)->not->toHaveKey('required');
    expect($responseSchema['properties'])->toHaveKey('foo');
    expect($responseSchema['properties'])->toHaveKey('bar');
});

// === No consumes at operation level in OAS3 ===

it('does not include consumes at operation level', function () {
    $config = V1Api::getOpenApiConfig('v1');
    $operation = $config['paths']['/v1/item/list']['get'];

    expect($operation)->not->toHaveKey('consumes');
});
