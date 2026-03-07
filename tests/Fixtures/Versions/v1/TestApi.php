<?php

declare(strict_types=1);

namespace Tests\Fixtures\Versions\v1;

use Dskripchenko\LaravelApi\Components\BaseApi;
use Tests\Fixtures\Middleware\TestAuthMiddleware;
use Tests\Fixtures\Middleware\TestLogMiddleware;
use Tests\Fixtures\Versions\v1\Controllers\ItemController;
use Tests\Fixtures\Versions\v1\Controllers\OpenController;

/**
 * Test API v1
 *
 * Test API description
 */
class TestApi extends BaseApi
{
    public static function getMethods(): array
    {
        return [
            'middleware' => [TestLogMiddleware::class],
            'controllers' => [
                'item' => [
                    'controller' => ItemController::class,
                    'middleware' => [TestAuthMiddleware::class],
                    'actions' => [
                        'list' => [
                            'action' => 'list',
                            'method' => 'get',
                        ],
                        'show' => [
                            'action' => 'show',
                            'method' => 'get',
                        ],
                        'create' => [
                            'action' => 'create',
                            'method' => 'post',
                        ],
                        'update' => [
                            'action' => 'update',
                            'method' => 'post',
                        ],
                        'remove' => 'delete',
                        'disabled' => false,
                    ],
                ],
                'open' => [
                    'controller' => OpenController::class,
                    'actions' => [
                        'ping' => [
                            'action' => 'ping',
                            'method' => 'get',
                        ],
                    ],
                ],
            ],
        ];
    }
}
