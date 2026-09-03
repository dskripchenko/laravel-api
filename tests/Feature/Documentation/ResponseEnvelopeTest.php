<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Services\OpenApiTypeScriptGenerator;
use Tests\Fixtures\OpenApi\CustomEnvelopeApi;
use Tests\Fixtures\OpenApi\EnvelopeApi;
use Tests\Fixtures\OpenApi\TemplateApi;

function envelopeSchema(array $config, string $path, string $method, string $code = '200'): array
{
    return $config['paths'][$path][$method]['responses'][$code]['content']['application/json']['schema'];
}

it('wraps an @output {Template} in the envelope', function () {
    $schema = envelopeSchema(EnvelopeApi::getOpenApiConfig('v1'), '/v1/agreement/available', 'get');

    expect($schema['type'])->toBe('object');
    expect($schema['properties']['success'])->toBe(['type' => 'boolean']);
    expect($schema['properties']['payload'])->toBe(['$ref' => '#/components/schemas/ProlongationAvailable']);
    expect($schema['required'])->toBe(['success', 'payload']);
});

it('wraps flat @output fields in the envelope', function () {
    $schema = envelopeSchema(EnvelopeApi::getOpenApiConfig('v1'), '/v1/agreement/fields', 'get');

    $payload = $schema['properties']['payload'];
    expect($payload['type'])->toBe('object');
    expect($payload['properties']['url']['type'])->toBe('string');
    expect($payload['properties']['comment']['type'])->toBe('string');
    expect($payload['required'])->toBe(['url']);
    expect($schema['required'])->toBe(['success', 'payload']);
});

it('wraps every @response that has a schema, error codes included', function () {
    $config = EnvelopeApi::getOpenApiConfig('v1');

    $created = envelopeSchema($config, '/v1/agreement/create', 'post', '201');
    expect($created['properties']['payload'])->toBe(['$ref' => '#/components/schemas/ProlongationAvailable']);

    // The runtime wraps an error the same way — {success: false, payload: {...}}.
    $failed = envelopeSchema($config, '/v1/agreement/create', 'post', '422');
    expect($failed['properties']['success'])->toBe(['type' => 'boolean']);
    expect($failed['properties']['payload'])->toBe(['$ref' => '#/components/schemas/ValidationError']);

    // A description-only response has no schema, so there is nothing to wrap.
    expect($config['paths']['/v1/agreement/create']['post']['responses']['404'])->toBe(['description' => 'Not found']);
});

it('wraps an undocumented response as an empty payload', function () {
    $schema = envelopeSchema(EnvelopeApi::getOpenApiConfig('v1'), '/v1/agreement/plain', 'get');

    expect($schema['properties']['payload']['type'])->toBe('object');
    expect($schema['required'])->toBe(['success', 'payload']);
});

it('documents the default Error and Success in the envelope', function () {
    $schemas = EnvelopeApi::getOpenApiConfig('v1')['components']['schemas'];

    $error = $schemas['Error'];
    expect($error['properties']['success'])->toBe(['type' => 'boolean']);
    expect($error['properties']['payload']['properties']['errorKey'])->toBe(['type' => 'string']);
    expect($error['properties']['payload']['properties']['message'])->toBe(['type' => 'string']);
    expect($error['required'])->toBe(['success', 'payload']);

    $success = $schemas['Success'];
    expect($success['properties']['payload']['type'])->toBe('object');
    expect($success['properties']['payload'])->not->toHaveKey('properties');
});

it('leaves a payload template as a payload', function () {
    $schema = EnvelopeApi::getOpenApiConfig('v1')['components']['schemas']['ProlongationAvailable'];

    expect($schema['properties'])->not->toHaveKey('success');
    expect(array_keys($schema['properties']))->toBe(['url', 'uuid', 'clientEmail', 'clientPhone']);
    expect($schema['required'])->toBe(['uuid']);
});

it('keeps the responses unwrapped when the envelope is off', function () {
    $schema = envelopeSchema(TemplateApi::getOpenApiConfig('v1'), '/v1/template/getUser', 'get');

    expect($schema)->toBe(['$ref' => '#/components/schemas/UserResponse']);
});

it('takes a custom envelope with {payload} at any depth', function () {
    $config = CustomEnvelopeApi::getOpenApiConfig('v1');
    $schema = envelopeSchema($config, '/v1/agreement/available', 'get');

    expect(array_keys($schema['properties']))->toBe(['ok', 'data', 'meta']);
    expect($schema['properties']['ok'])->toBe(['type' => 'boolean', 'description' => 'Whether the call succeeded']);
    expect($schema['properties']['data'])->toBe(['$ref' => '#/components/schemas/ProlongationAvailable']);
    expect($schema['properties']['meta'])->toBe(['$ref' => '#/components/schemas/Meta']);
    expect($schema['required'])->toBe(['ok', 'data']);
});

it('takes an own Error as the payload of the error answer', function () {
    $error = CustomEnvelopeApi::getOpenApiConfig('v1')['components']['schemas']['Error'];

    expect(array_keys($error['properties']))->toBe(['ok', 'data', 'meta']);
    expect($error['properties']['data']['properties'])->toHaveKey('trace');
    expect($error['properties']['data']['required'])->toBe(['errorKey', 'message']);
});

it('types the enveloped output in TypeScript', function () {
    $ts = (new OpenApiTypeScriptGenerator())->generate(EnvelopeApi::getOpenApiConfig('v1'));

    expect($ts)->toContain('success: boolean;');
    expect($ts)->toContain('payload: ProlongationAvailable;');
});
