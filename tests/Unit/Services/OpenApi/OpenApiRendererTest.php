<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Operations\DescriptorBuilder;
use Dskripchenko\LaravelApi\Services\OpenApi\OpenApiRenderer;
use Tests\Fixtures\OpenApi\MultiMethodApi;
use Tests\Fixtures\Versions\v1\TestApi;

it('is what getOpenApiConfig() returns', function () {
    expect((new OpenApiRenderer(TestApi::class))->render('v1'))->toBe(TestApi::getOpenApiConfig('v1'));
});

it('renders the paths alone', function () {
    $renderer = new OpenApiRenderer(TestApi::class);

    expect($renderer->paths('v1'))->toBe($renderer->render('v1')['paths']);
});

it('renders one descriptor as one operation of the paths', function () {
    $renderer = new OpenApiRenderer(MultiMethodApi::class);
    $paths = $renderer->paths('v1');

    $path = array_key_first($paths);
    [$controller, $action] = array_slice(explode('/', $path), -2);
    $operation = MultiMethodApi::operations('v1')->find($controller, $action);

    foreach (DescriptorBuilder::for(MultiMethodApi::class)->build($operation) as $descriptor) {
        expect($renderer->operation($descriptor))->toBe($paths[$path][$descriptor->httpMethod]);
    }
});

/**
 * Three ways the served document used to fail OpenAPI 3.0 validation
 * (checked with @apidevtools/swagger-parser): `"properties": []` — PHP's empty
 * array as a JSON list —, `"required": []` — draft 4 wants at least one name
 * —, and a `name` key the generator left inside the schemas of output fields.
 */
it('serves documents free of what OpenAPI 3.0 rejects', function (string $api, string $version) {
    $served = json_decode(json_encode(
        \Dskripchenko\LaravelApi\Operations\Schema\JsonSchemas::encodable((new OpenApiRenderer($api))->render($version))
    ), false);

    $problems = [];
    $walk = static function (mixed $node, string $path) use (&$walk, &$problems): void {
        if (is_array($node)) {
            foreach ($node as $i => $item) {
                $walk($item, "{$path}[{$i}]");
            }

            return;
        }
        if (!is_object($node)) {
            return;
        }
        foreach (get_object_vars($node) as $key => $value) {
            if ($key === 'properties' && is_array($value)) {
                $problems[] = "{$path}.properties is a list";
            }
            if ($key === 'required' && $value === []) {
                $problems[] = "{$path}.required is empty";
            }
            if ($key === 'properties' && is_object($value)) {
                foreach (get_object_vars($value) as $field => $schema) {
                    if (is_object($schema) && property_exists($schema, 'name')) {
                        $problems[] = "{$path}.properties.{$field} has a name";
                    }
                }
            }
            $walk($value, "{$path}.{$key}");
        }
    };
    $walk($served, '$');

    expect($problems)->toBe([]);
})->with([
    'fixture v1' => [TestApi::class, 'v1'],
    'template' => [\Tests\Fixtures\OpenApi\TemplateApi::class, 'v1'],
    'extended' => [\Tests\Fixtures\OpenApi\ExtendedApi::class, 'v1'],
    'context' => [\Tests\Fixtures\OpenApi\ContextApi::class, 'v1'],
    'envelope' => [\Tests\Fixtures\OpenApi\EnvelopeApi::class, 'v1'],
    'custom envelope' => [\Tests\Fixtures\OpenApi\CustomEnvelopeApi::class, 'v1'],
    'shorthand' => [\Tests\Fixtures\OpenApi\ShorthandApi::class, 'v1'],
    'example v1' => [\Dskripchenko\LaravelApiExample\Versions\v1\Api::class, 'v1'],
]);
