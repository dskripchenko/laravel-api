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
