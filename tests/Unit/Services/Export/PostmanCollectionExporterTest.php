<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Services\Export\PostmanCollectionExporter;
use Tests\Fixtures\OpenApi\ExtendedApi;

it('generates valid postman collection v2.1 structure', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $exporter = new PostmanCollectionExporter();
    $result = $exporter->export($config, 'v1');
    $collection = json_decode($result, true);

    expect($collection['info']['schema'])->toBe('https://schema.getpostman.com/json/collection/v2.1.0/collection.json');
    expect($collection['info']['name'])->toContain('v1');
    expect($collection['item'])->toBeArray();
    expect($collection['item'])->not->toBeEmpty();
    expect($collection['variable'])->toBeArray();
});

it('groups items by controller tag', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $exporter = new PostmanCollectionExporter();
    $result = $exporter->export($config, 'v1');
    $collection = json_decode($result, true);

    $folderNames = array_column($collection['item'], 'name');
    expect($folderNames)->toContain('extended');
});

it('includes request method and url', function () {
    $config = [
        'info' => ['title' => 'Test', 'description' => ''],
        'servers' => [['url' => 'http://localhost/api']],
        'paths' => [
            '/v1/user/list' => [
                'get' => [
                    'summary' => 'List users',
                    'tags' => ['user'],
                    'operationId' => 'user_list',
                    'parameters' => [
                        ['name' => 'page', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer'], 'example' => 1],
                    ],
                    'responses' => [],
                ],
            ],
        ],
    ];

    $exporter = new PostmanCollectionExporter();
    $result = $exporter->export($config, 'v1');
    $collection = json_decode($result, true);

    $item = $collection['item'][0]['item'][0];
    expect($item['request']['method'])->toBe('GET');
    expect($item['request']['url']['raw'])->toContain('/v1/user/list');
    expect($item['request']['url']['query'][0]['key'])->toBe('page');
});

it('handles json request body', function () {
    $config = [
        'info' => ['title' => 'Test', 'description' => ''],
        'servers' => [['url' => 'http://localhost/api']],
        'paths' => [
            '/v1/user/create' => [
                'post' => [
                    'summary' => 'Create user',
                    'tags' => ['user'],
                    'operationId' => 'user_create',
                    'parameters' => [],
                    'requestBody' => [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => ['type' => 'string'],
                                        'age' => ['type' => 'integer'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [],
                ],
            ],
        ],
    ];

    $exporter = new PostmanCollectionExporter();
    $result = $exporter->export($config, 'v1');
    $collection = json_decode($result, true);

    $item = $collection['item'][0]['item'][0];
    expect($item['request']['body']['mode'])->toBe('raw');
    expect($item['request']['body']['options']['raw']['language'])->toBe('json');

    $body = json_decode($item['request']['body']['raw'], true);
    expect($body)->toHaveKey('name');
    expect($body)->toHaveKey('age');
});

it('handles multipart form data', function () {
    $config = [
        'info' => ['title' => 'Test', 'description' => ''],
        'servers' => [['url' => 'http://localhost/api']],
        'paths' => [
            '/v1/upload' => [
                'post' => [
                    'summary' => 'Upload',
                    'tags' => ['upload'],
                    'operationId' => 'upload',
                    'parameters' => [],
                    'requestBody' => [
                        'content' => [
                            'multipart/form-data' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'avatar' => ['type' => 'string', 'format' => 'binary'],
                                        'name' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [],
                ],
            ],
        ],
    ];

    $exporter = new PostmanCollectionExporter();
    $result = $exporter->export($config, 'v1');
    $collection = json_decode($result, true);

    $item = $collection['item'][0]['item'][0];
    expect($item['request']['body']['mode'])->toBe('formdata');

    $avatarField = collect($item['request']['body']['formdata'])->firstWhere('key', 'avatar');
    expect($avatarField['type'])->toBe('file');
});
