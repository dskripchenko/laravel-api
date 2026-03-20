<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Services\Export\HttpClientExporter;
use Tests\Fixtures\OpenApi\ExtendedApi;

it('generates http client format with host variable', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $exporter = new HttpClientExporter();
    $result = $exporter->export($config, 'v1');

    expect($result)->toContain('@host');
    expect($result)->toContain('###');
});

it('includes GET requests with query params', function () {
    $config = [
        'info' => ['title' => 'Test', 'description' => ''],
        'servers' => [['url' => 'http://localhost/api']],
        'paths' => [
            '/v1/user/list' => [
                'get' => [
                    'summary' => 'List users',
                    'parameters' => [
                        ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer'], 'example' => 1],
                    ],
                    'responses' => [],
                ],
            ],
        ],
    ];

    $exporter = new HttpClientExporter();
    $result = $exporter->export($config, 'v1');

    expect($result)->toContain('### List users');
    expect($result)->toContain('GET {{host}}/v1/user/list?page=1');
});

it('includes POST with json body', function () {
    $config = [
        'info' => ['title' => 'Test', 'description' => ''],
        'servers' => [['url' => 'http://localhost/api']],
        'paths' => [
            '/v1/user/create' => [
                'post' => [
                    'summary' => 'Create user',
                    'parameters' => [],
                    'requestBody' => [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
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

    $exporter = new HttpClientExporter();
    $result = $exporter->export($config, 'v1');

    expect($result)->toContain('POST {{host}}/v1/user/create');
    expect($result)->toContain('Content-Type: application/json');
    expect($result)->toContain('"name"');
});

it('includes urlencoded body', function () {
    $config = [
        'info' => ['title' => 'Test', 'description' => ''],
        'servers' => [['url' => 'http://localhost/api']],
        'paths' => [
            '/v1/user/update' => [
                'post' => [
                    'summary' => 'Update user',
                    'parameters' => [],
                    'requestBody' => [
                        'content' => [
                            'application/x-www-form-urlencoded' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => ['type' => 'string', 'example' => 'John'],
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

    $exporter = new HttpClientExporter();
    $result = $exporter->export($config, 'v1');

    expect($result)->toContain('Content-Type: application/x-www-form-urlencoded');
    expect($result)->toContain('name=John');
});
