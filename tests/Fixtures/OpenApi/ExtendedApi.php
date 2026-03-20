<?php

declare(strict_types=1);

namespace Tests\Fixtures\OpenApi;

use Dskripchenko\LaravelApi\Components\BaseApi;

/**
 * Extended API
 * API with extended OpenAPI features
 */
class ExtendedApi extends BaseApi
{
    public static $useResponseTemplates = true;

    public static function getMethods(): array
    {
        return [
            'controllers' => [
                'extended' => [
                    'controller' => ExtendedController::class,
                    'actions' => [
                        'headerAction' => [
                            'action' => 'headerAction',
                            'method' => 'post',
                        ],
                        'securityAction' => [
                            'action' => 'securityAction',
                            'method' => 'post',
                            'security' => [['BearerAuth' => []]],
                        ],
                        'deprecatedAction' => [
                            'action' => 'deprecatedAction',
                            'method' => 'post',
                        ],
                        'multiResponseAction' => [
                            'action' => 'multiResponseAction',
                            'method' => 'get',
                        ],
                        'nestedInputAction' => [
                            'action' => 'nestedInputAction',
                            'method' => 'post',
                        ],
                        'formatAction' => [
                            'action' => 'formatAction',
                            'method' => 'post',
                        ],
                        'enumAction' => [
                            'action' => 'enumAction',
                            'method' => 'post',
                        ],
                        'modelRefAction' => [
                            'action' => 'modelRefAction',
                            'method' => 'post',
                        ],
                        'defaultExampleAction' => [
                            'action' => 'defaultExampleAction',
                            'method' => 'get',
                        ],
                        'fileUploadAction' => [
                            'action' => 'fileUploadAction',
                            'method' => 'post',
                        ],
                        'optionalOutputAction' => [
                            'action' => 'optionalOutputAction',
                            'method' => 'get',
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function getOpenApiSecurityDefinitions(): array
    {
        return [
            'BearerAuth' => [
                'type' => 'apiKey',
                'name' => 'Authorization',
                'in' => 'header',
            ],
        ];
    }

    public static function getOpenApiTemplates(): array
    {
        return [
            'UserResponse' => [
                'id' => ['type' => 'integer', 'required' => true],
                'name' => ['type' => 'string', 'required' => true],
                'email' => ['type' => 'string'],
            ],
            'ValidationError' => [
                'message' => ['type' => 'string', 'required' => true],
                'errors' => ['type' => 'object'],
            ],
            'OrderCreateRequest' => [
                'product_id' => ['type' => 'integer', 'required' => true],
                'quantity' => ['type' => 'integer', 'required' => true],
            ],
            'User' => [
                'id' => ['type' => 'integer', 'required' => true],
                'name' => ['type' => 'string', 'required' => true],
            ],
        ];
    }
}
