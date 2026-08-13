<?php

declare(strict_types=1);

namespace Tests\Fixtures\Versions\v2;

use Tests\Fixtures\Middleware\TestLogMiddleware;

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
            // A check that the version-level exclusions reach the route and
            // remove the middleware that came from the shared group.
            'exclude-middleware' => [TestLogMiddleware::class],
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
