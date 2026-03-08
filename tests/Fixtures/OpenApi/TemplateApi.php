<?php

declare(strict_types=1);

namespace Tests\Fixtures\OpenApi;

use Dskripchenko\LaravelApi\Components\BaseApi;

/**
 * Template API
 * API with response templates
 */
class TemplateApi extends BaseApi
{
    public static $useResponseTemplates = true;

    public static function getMethods(): array
    {
        return [
            'controllers' => [
                'template' => [
                    'controller' => TemplateController::class,
                    'actions' => [
                        'getUser' => [
                            'action' => 'getUser',
                            'method' => 'get',
                        ],
                        'createUser' => [
                            'action' => 'createUser',
                            'method' => 'post',
                        ],
                    ],
                ],
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
        ];
    }
}
