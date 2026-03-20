<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Services\OpenApiTypeScriptGenerator;

beforeEach(function () {
    $this->generator = new OpenApiTypeScriptGenerator();
});

it('generates interface from schema with required and optional fields', function () {
    $config = [
        'components' => [
            'schemas' => [
                'User' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                        'email' => ['type' => 'string', 'format' => 'email'],
                    ],
                    'required' => ['id', 'name'],
                ],
            ],
        ],
        'paths' => [],
    ];

    $result = $this->generator->generate($config);

    expect($result)->toContain('export interface User {');
    expect($result)->toContain('id: number;');
    expect($result)->toContain('name: string;');
    expect($result)->toContain('email?: string;');
});

it('generates input and output types from operation', function () {
    $config = [
        'paths' => [
            '/v1/user/show' => [
                'get' => [
                    'operationId' => 'user_show',
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'query',
                            'required' => true,
                            'schema' => ['type' => 'integer'],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'id' => ['type' => 'integer'],
                                            'name' => ['type' => 'string'],
                                        ],
                                        'required' => ['id', 'name'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $result = $this->generator->generate($config);

    expect($result)->toContain('export interface UserShowInput {');
    expect($result)->toContain('id: number;');
    expect($result)->toContain('export interface UserShowOutput {');
});

it('maps primitive types correctly', function () {
    $config = [
        'components' => [
            'schemas' => [
                'AllTypes' => [
                    'type' => 'object',
                    'properties' => [
                        'str' => ['type' => 'string'],
                        'int' => ['type' => 'integer'],
                        'num' => ['type' => 'number'],
                        'bool' => ['type' => 'boolean'],
                    ],
                    'required' => ['str', 'int', 'num', 'bool'],
                ],
            ],
        ],
        'paths' => [],
    ];

    $result = $this->generator->generate($config);

    expect($result)->toContain('str: string;');
    expect($result)->toContain('int: number;');
    expect($result)->toContain('num: number;');
    expect($result)->toContain('bool: boolean;');
});

it('handles $ref in properties', function () {
    $config = [
        'components' => [
            'schemas' => [
                'Order' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'customer' => ['$ref' => '#/components/schemas/Customer'],
                    ],
                    'required' => ['id', 'customer'],
                ],
                'Customer' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                    ],
                    'required' => ['name'],
                ],
            ],
        ],
        'paths' => [],
    ];

    $result = $this->generator->generate($config);

    expect($result)->toContain('customer: Customer;');
});

it('handles array of $ref', function () {
    $config = [
        'components' => [
            'schemas' => [
                'OrderList' => [
                    'type' => 'object',
                    'properties' => [
                        'items' => [
                            'type' => 'array',
                            'items' => ['$ref' => '#/components/schemas/Order'],
                        ],
                    ],
                    'required' => ['items'],
                ],
            ],
        ],
        'paths' => [],
    ];

    $result = $this->generator->generate($config);

    expect($result)->toContain('items: Order[];');
});

it('handles enum as union type', function () {
    $config = [
        'components' => [
            'schemas' => [
                'StatusObj' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => [
                            'type' => 'string',
                            'enum' => ['active', 'blocked', 'pending'],
                        ],
                    ],
                    'required' => ['status'],
                ],
            ],
        ],
        'paths' => [],
    ];

    $result = $this->generator->generate($config);

    expect($result)->toContain("status: 'active' | 'blocked' | 'pending';");
});

it('handles nested object properties', function () {
    $config = [
        'paths' => [
            '/v1/user/create' => [
                'post' => [
                    'operationId' => 'user_create',
                    'parameters' => [],
                    'requestBody' => [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'address' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'city' => ['type' => 'string'],
                                                'zip' => ['type' => 'string'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'id' => ['type' => 'integer'],
                                        ],
                                        'required' => ['id'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $result = $this->generator->generate($config);

    expect($result)->toContain('export interface UserCreateInput {');
    expect($result)->toContain('address?:');
    expect($result)->toContain('city?:');
});

it('handles file type as File', function () {
    $config = [
        'paths' => [
            '/v1/upload/avatar' => [
                'post' => [
                    'operationId' => 'upload_avatar',
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
                                    'required' => ['avatar'],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'url' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $result = $this->generator->generate($config);

    expect($result)->toContain('avatar: File;');
});

it('generates $ref input when requestBody uses $ref', function () {
    $config = [
        'paths' => [
            '/v1/order/create' => [
                'post' => [
                    'operationId' => 'order_create',
                    'parameters' => [],
                    'requestBody' => [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/OrderCreateRequest',
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'id' => ['type' => 'integer'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $result = $this->generator->generate($config);

    expect($result)->toContain('export type OrderCreateInput = OrderCreateRequest;');
});

it('skips header parameters in input', function () {
    $config = [
        'paths' => [
            '/v1/user/me' => [
                'get' => [
                    'operationId' => 'user_me',
                    'parameters' => [
                        [
                            'name' => 'Authorization',
                            'in' => 'header',
                            'required' => true,
                            'schema' => ['type' => 'string'],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'id' => ['type' => 'integer'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $result = $this->generator->generate($config);

    expect($result)->not->toContain('UserMeInput');
    expect($result)->toContain('UserMeOutput');
});

it('adds format as JSDoc comment', function () {
    $config = [
        'components' => [
            'schemas' => [
                'Event' => [
                    'type' => 'object',
                    'properties' => [
                        'createdAt' => [
                            'type' => 'string',
                            'format' => 'date-time',
                            'description' => 'Creation timestamp',
                        ],
                    ],
                    'required' => ['createdAt'],
                ],
            ],
        ],
        'paths' => [],
    ];

    $result = $this->generator->generate($config);

    expect($result)->toContain('@format date-time');
    expect($result)->toContain('Creation timestamp');
});

it('handles empty object as Record<string, unknown>', function () {
    $config = [
        'components' => [
            'schemas' => [
                'Meta' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => ['type' => 'object'],
                    ],
                    'required' => ['data'],
                ],
            ],
        ],
        'paths' => [],
    ];

    $result = $this->generator->generate($config);

    expect($result)->toContain('data: Record<string, unknown>;');
});

it('generates auto-generated header comment', function () {
    $result = $this->generator->generate(['paths' => []]);

    expect($result)->toContain('Auto-generated from OpenAPI spec');
    expect($result)->toContain('Do not edit manually');
});

it('handles output with $ref schema', function () {
    $config = [
        'paths' => [
            '/v1/user/show' => [
                'get' => [
                    'operationId' => 'user_show',
                    'parameters' => [],
                    'responses' => [
                        '200' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/UserResponse',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $result = $this->generator->generate($config);

    expect($result)->toContain('export type UserShowOutput = UserResponse;');
});
