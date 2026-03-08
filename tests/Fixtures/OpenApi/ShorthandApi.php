<?php

declare(strict_types=1);

namespace Tests\Fixtures\OpenApi;

use Dskripchenko\LaravelApi\Components\BaseApi;

/**
 * Shorthand API
 * API with shorthand template syntax
 */
class ShorthandApi extends BaseApi
{
    public static $useResponseTemplates = true;

    public static function getMethods(): array
    {
        return [
            'controllers' => [
                'order' => [
                    'controller' => ShorthandController::class,
                    'actions' => [
                        'create' => ['method' => 'post'],
                    ],
                ],
            ],
        ];
    }

    public static function getOpenApiTemplates(): array
    {
        return [
            'OrderResponse' => [
                'id' => 'integer!',
                'status' => 'string!',
                'total' => 'number',
                'created_at' => 'string(date-time)',
                'email' => 'string(email)!',
            ],
            'OrderError' => [
                'message' => 'string!',
                'code' => 'integer',
            ],
            // Mixed: shorthand + array format (backward compat)
            'MixedTemplate' => [
                'id' => 'integer!',
                'name' => ['type' => 'string', 'required' => true],
                'score' => 'number',
            ],
            // Ref shorthand
            'OrderWithRef' => [
                'id' => 'integer!',
                'error' => '@OrderError',
                'items' => '@OrderItem[]',
            ],
            'OrderItem' => [
                'product_id' => 'integer!',
                'quantity' => 'integer',
                'price' => 'number',
            ],
        ];
    }
}
