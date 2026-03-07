<?php

declare(strict_types=1);

namespace Tests\Fixtures\Versions\v2;

use Tests\Fixtures\Versions\v1\TestApi as V1Api;
use Tests\Fixtures\Versions\v2\Controllers\ItemController;

/**
 * Test API v2
 *
 * Test API v2 description
 */
class TestApi extends V1Api
{
    public static function getMethods(): array
    {
        return [
            'controllers' => [
                'item' => [
                    'controller' => ItemController::class,
                    'actions' => [
                        'search' => [
                            'action' => 'search',
                            'method' => 'get',
                        ],
                        'remove' => false,
                    ],
                ],
            ],
        ];
    }
}
