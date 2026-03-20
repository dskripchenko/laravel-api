<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Services\Export\MarkdownExporter;
use Tests\Fixtures\OpenApi\ExtendedApi;

it('generates markdown with title and TOC', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $exporter = new MarkdownExporter();
    $result = $exporter->export($config, 'v1');

    expect($result)->toContain('# ');
    expect($result)->toContain('## Table of Contents');
    expect($result)->toContain('## extended');
});

it('includes endpoint method and path', function () {
    $config = [
        'info' => ['title' => 'Test API', 'description' => 'Test'],
        'servers' => [['url' => 'http://localhost/api']],
        'paths' => [
            '/v1/user/list' => [
                'get' => [
                    'summary' => 'List users',
                    'description' => '',
                    'tags' => ['user'],
                    'parameters' => [
                        ['name' => 'page', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer'], 'description' => 'Page number'],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Success'],
                    ],
                ],
            ],
        ],
    ];

    $exporter = new MarkdownExporter();
    $result = $exporter->export($config, 'v1');

    expect($result)->toContain('`GET /v1/user/list`');
    expect($result)->toContain('### List users');
    expect($result)->toContain('| `page` | integer | No | Page number |');
    expect($result)->toContain('`200`');
});

it('shows request body fields', function () {
    $config = [
        'info' => ['title' => 'Test', 'description' => ''],
        'servers' => [['url' => 'http://localhost/api']],
        'paths' => [
            '/v1/user/create' => [
                'post' => [
                    'summary' => 'Create user',
                    'description' => '',
                    'tags' => ['user'],
                    'parameters' => [],
                    'requestBody' => [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => ['type' => 'string', 'description' => 'User name'],
                                        'email' => ['type' => 'string', 'description' => 'Email'],
                                    ],
                                    'required' => ['name'],
                                ],
                            ],
                        ],
                    ],
                    'responses' => ['200' => ['description' => 'Created']],
                ],
            ],
        ],
    ];

    $exporter = new MarkdownExporter();
    $result = $exporter->export($config, 'v1');

    expect($result)->toContain('**Request Body:**');
    expect($result)->toContain('| `name` | string | Yes | User name |');
    expect($result)->toContain('| `email` | string | No | Email |');
});

it('marks deprecated endpoints', function () {
    $config = [
        'info' => ['title' => 'Test', 'description' => ''],
        'servers' => [],
        'paths' => [
            '/v1/old/action' => [
                'post' => [
                    'summary' => 'Old action',
                    'description' => '',
                    'tags' => ['old'],
                    'deprecated' => true,
                    'parameters' => [],
                    'responses' => [],
                ],
            ],
        ],
    ];

    $exporter = new MarkdownExporter();
    $result = $exporter->export($config, 'v1');

    expect($result)->toContain('~~Old action~~ (deprecated)');
});

it('includes header parameters', function () {
    $config = [
        'info' => ['title' => 'Test', 'description' => ''],
        'servers' => [],
        'paths' => [
            '/v1/secure/action' => [
                'post' => [
                    'summary' => 'Secure action',
                    'description' => '',
                    'tags' => ['secure'],
                    'parameters' => [
                        ['name' => 'Authorization', 'in' => 'header', 'required' => true, 'description' => 'Bearer token'],
                    ],
                    'responses' => [],
                ],
            ],
        ],
    ];

    $exporter = new MarkdownExporter();
    $result = $exporter->export($config, 'v1');

    expect($result)->toContain('**Headers:**');
    expect($result)->toContain('| `Authorization` | Yes | Bearer token |');
});
