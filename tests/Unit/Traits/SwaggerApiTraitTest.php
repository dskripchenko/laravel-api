<?php

declare(strict_types=1);

use Tests\Fixtures\Versions\v1\TestApi as V1Api;
use Tests\Fixtures\Swagger\TemplateApi;
use Tests\Fixtures\Swagger\ExtendedApi;
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

// === Phase 1: Format, Enum, operationId, deprecated ===

it('parses format from type parentheses for input', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');
    $params = $config['paths']['/v1/extended/formatAction']['post']['parameters'];
    $emailParam = collect($params)->firstWhere('name', 'email');
    $bigIdParam = collect($params)->firstWhere('name', 'bigId');

    expect($emailParam['type'])->toBe('string');
    expect($emailParam['format'])->toBe('email');
    expect($bigIdParam['type'])->toBe('integer');
    expect($bigIdParam['format'])->toBe('int64');
});

it('parses format for output', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');
    $response = $config['paths']['/v1/extended/formatAction']['post']['responses']['payload'];
    $props = $response['properties'];

    expect($props['createdAt']['format'])->toBe('date-time');
    expect($props['count']['format'])->toBe('int32');
});

it('ignores format when no parentheses', function () {
    $config = V1Api::getSwaggerApiConfig('v1');
    $params = $config['paths']['/v1/item/list']['get']['parameters'];
    $pageParam = collect($params)->firstWhere('name', 'page');

    expect($pageParam)->not->toHaveKey('format');
});

it('extracts enum from description brackets', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');
    $params = $config['paths']['/v1/extended/enumAction']['post']['parameters'];
    $statusParam = collect($params)->firstWhere('name', 'status');

    expect($statusParam['enum'])->toBe(['active', 'blocked', 'pending']);
});

it('strips enum brackets from description', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');
    $params = $config['paths']['/v1/extended/enumAction']['post']['parameters'];
    $statusParam = collect($params)->firstWhere('name', 'status');

    expect($statusParam['description'])->toBe('Status');
    expect($statusParam['description'])->not->toContain('[');
});

it('generates operationId', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');
    $method = $config['paths']['/v1/extended/formatAction']['post'];

    expect($method['operationId'])->toBe('extended_formatAction');
});

it('sets deprecated flag', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');
    $method = $config['paths']['/v1/extended/deprecatedAction']['post'];

    expect($method['deprecated'])->toBeTrue();
});

it('no deprecated when absent', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');
    $method = $config['paths']['/v1/extended/formatAction']['post'];

    expect($method)->not->toHaveKey('deprecated');
});

// === Phase 2: Headers, Responses, Security ===

it('parses @header into header parameters', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');
    $params = $config['paths']['/v1/extended/headerAction']['post']['parameters'];
    $authParam = collect($params)->firstWhere('name', 'Authorization');
    $reqIdParam = collect($params)->firstWhere('name', 'X-Request-Id');

    expect($authParam['in'])->toBe('header');
    expect($authParam['required'])->toBeTrue();
    expect($reqIdParam['in'])->toBe('header');
    expect($reqIdParam['required'])->toBeFalse();
});

it('aggregates @header from middleware', function () {
    // Create a temporary API class that uses HeaderMiddleware
    $apiClass = new class extends BaseApi {
        public static $useResponseTemplates = false;

        public static function getMethods(): array
        {
            return [
                'controllers' => [
                    'extended' => [
                        'controller' => \Tests\Fixtures\Swagger\ExtendedController::class,
                        'middleware' => [\Tests\Fixtures\Swagger\HeaderMiddleware::class],
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

    $config = $apiClass::getSwaggerApiConfig('v1');
    $params = $config['paths']['/v1/extended/headerAction']['post']['parameters'];
    $headerParams = collect($params)->where('in', 'header');

    // Should have headers from both middleware and docblock
    $names = $headerParams->pluck('name')->toArray();
    expect($names)->toContain('X-Auth-Token');
    expect($names)->toContain('Authorization');
});

it('parses @response with HTTP codes', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');
    $responses = $config['paths']['/v1/extended/multiResponseAction']['get']['responses'];

    expect($responses)->toHaveKey('200');
    expect($responses)->toHaveKey('422');
});

it('uses template ref in @response', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');
    $responses = $config['paths']['/v1/extended/multiResponseAction']['get']['responses'];

    expect($responses['200']['schema']['$ref'])->toBe('#/definitions/UserResponse');
    expect($responses['422']['schema']['$ref'])->toBe('#/definitions/ValidationError');
});

it('falls back to @output when no @response', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');
    $responses = $config['paths']['/v1/extended/formatAction']['post']['responses'];

    expect($responses)->toHaveKey('payload');
    expect($responses['payload']['properties'])->toHaveKey('createdAt');
});

it('includes securityDefinitions', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');

    expect($config)->toHaveKey('securityDefinitions');
    expect($config['securityDefinitions'])->toHaveKey('BearerAuth');
    expect($config['securityDefinitions']['BearerAuth']['type'])->toBe('apiKey');
});

it('parses @security tag', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');
    $method = $config['paths']['/v1/extended/securityAction']['post'];

    expect($method['security'])->toBe([['BearerAuth' => []]]);
});

it('no securityDefinitions when empty', function () {
    $config = V1Api::getSwaggerApiConfig('v1');

    expect($config)->not->toHaveKey('securityDefinitions');
});

// === Phase 3: Nested, Auto-consumes ===

it('dot-notation inputs to body param with nested schema', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');
    $params = $config['paths']['/v1/extended/nestedInputAction']['post']['parameters'];
    $bodyParam = collect($params)->firstWhere('in', 'body');

    expect($bodyParam)->not->toBeNull();
    expect($bodyParam['schema']['type'])->toBe('object');
    expect($bodyParam['schema']['properties'])->toHaveKey('address');
    expect($bodyParam['schema']['properties']['address']['type'])->toBe('object');
    expect($bodyParam['schema']['properties']['address']['properties'])->toHaveKey('city');
});

it('array notation in dot-notation', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');
    $params = $config['paths']['/v1/extended/nestedInputAction']['post']['parameters'];
    $bodyParam = collect($params)->firstWhere('in', 'body');

    $tags = $bodyParam['schema']['properties']['tags'];
    expect($tags['type'])->toBe('array');
    expect($tags['items']['type'])->toBe('object');
    expect($tags['items']['properties'])->toHaveKey('id');
    expect($tags['items']['properties'])->toHaveKey('name');
});

it('flat inputs stay formData', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');
    $params = $config['paths']['/v1/extended/enumAction']['post']['parameters'];

    foreach ($params as $param) {
        expect($param['in'])->toBe('formData');
    }
});

it('dot-notation outputs nested', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');
    $response = $config['paths']['/v1/extended/nestedInputAction']['post']['responses']['payload'];

    expect($response['properties'])->toHaveKey('address');
    expect($response['properties']['address']['type'])->toBe('object');
    expect($response['properties']['address']['properties'])->toHaveKey('city');
});

it('consumes multipart for file', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');
    $method = $config['paths']['/v1/extended/fileUploadAction']['post'];

    expect($method['consumes'])->toBe(['multipart/form-data']);
});

it('consumes json for body param', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');
    $method = $config['paths']['/v1/extended/nestedInputAction']['post'];

    expect($method['consumes'])->toBe(['application/json']);
});

it('consumes urlencoded default', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');
    $method = $config['paths']['/v1/extended/enumAction']['post'];

    expect($method['consumes'])->toBe(['application/x-www-form-urlencoded']);
});

// === Phase 4: Model/Ref ===

it('body param $ref for @input @Model', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');
    $params = $config['paths']['/v1/extended/modelRefAction']['post']['parameters'];
    $bodyParam = collect($params)->firstWhere('in', 'body');

    expect($bodyParam)->not->toBeNull();
    expect($bodyParam['schema']['$ref'])->toBe('#/definitions/OrderCreateRequest');
});

it('output field $ref', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');
    $response = $config['paths']['/v1/extended/modelRefAction']['post']['responses']['payload'];

    expect($response['properties']['user']['$ref'])->toBe('#/definitions/User');
});

it('output array $ref', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');
    $response = $config['paths']['/v1/extended/modelRefAction']['post']['responses']['payload'];

    expect($response['properties']['users']['type'])->toBe('array');
    expect($response['properties']['users']['items']['$ref'])->toBe('#/definitions/User');
});

// === Phase 5: Default/Example ===

it('default from @default tag', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');
    $params = $config['paths']['/v1/extended/defaultExampleAction']['get']['parameters'];
    $pageParam = collect($params)->firstWhere('name', 'page');

    expect($pageParam['default'])->toBe(1);
});

it('x-example from @example tag', function () {
    $config = ExtendedApi::getSwaggerApiConfig('v1');
    $params = $config['paths']['/v1/extended/defaultExampleAction']['get']['parameters'];
    $pageParam = collect($params)->firstWhere('name', 'page');

    expect($pageParam['x-example'])->toBe(3);
});
