<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Services\Export\CurlExporter;
use Tests\Fixtures\OpenApi\ExtendedApi;

it('generates bash script with shebang', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $exporter = new CurlExporter();
    $result = $exporter->export($config, 'v1');

    expect($result)->toStartWith('#!/usr/bin/env bash');
    expect($result)->toContain('BASE_URL=');
    expect($result)->toContain('curl');
});

it('generates GET request with query params', function () {
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

    $exporter = new CurlExporter();
    $result = $exporter->export($config, 'v1');

    expect($result)->toContain('# List users');
    expect($result)->toContain('-X GET');
    expect($result)->toContain('/v1/user/list?page=1');
});

it('generates POST with json body', function () {
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

    $exporter = new CurlExporter();
    $result = $exporter->export($config, 'v1');

    expect($result)->toContain('-X POST');
    expect($result)->toContain('Content-Type: application/json');
    expect($result)->toContain("-d '{");
});

it('includes authorization header for secured endpoints', function () {
    $config = [
        'info' => ['title' => 'Test', 'description' => ''],
        'servers' => [['url' => 'http://localhost/api']],
        'paths' => [
            '/v1/me' => [
                'get' => [
                    'summary' => 'Current user',
                    'parameters' => [],
                    'security' => [['BearerAuth' => []]],
                    'responses' => [],
                ],
            ],
        ],
    ];

    $exporter = new CurlExporter();
    $result = $exporter->export($config, 'v1');

    expect($result)->toContain('Authorization: ${TOKEN}');
});

it('handles multipart file upload', function () {
    $config = [
        'info' => ['title' => 'Test', 'description' => ''],
        'servers' => [['url' => 'http://localhost/api']],
        'paths' => [
            '/v1/upload' => [
                'post' => [
                    'summary' => 'Upload file',
                    'parameters' => [],
                    'requestBody' => [
                        'content' => [
                            'multipart/form-data' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'avatar' => ['type' => 'string', 'format' => 'binary'],
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

    $exporter = new CurlExporter();
    $result = $exporter->export($config, 'v1');

    expect($result)->toContain('-F "avatar=@./path/to/avatar"');
});

it('handles urlencoded body', function () {
    $config = [
        'info' => ['title' => 'Test', 'description' => ''],
        'servers' => [['url' => 'http://localhost/api']],
        'paths' => [
            '/v1/user/update' => [
                'post' => [
                    'summary' => 'Update',
                    'parameters' => [],
                    'requestBody' => [
                        'content' => [
                            'application/x-www-form-urlencoded' => [
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

    $exporter = new CurlExporter();
    $result = $exporter->export($config, 'v1');

    expect($result)->toContain('-d "name="');
});
