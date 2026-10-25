<?php

declare(strict_types=1);

namespace Tests\Fixtures\OpenApi;

use Dskripchenko\LaravelApi\Components\BaseApi;
use Tests\Fixtures\Versions\v1\Controllers\OpenController;

/**
 * Multi-method API
 * One action served by GET and POST: two operations, two operationIds.
 */
class MultiMethodApi extends BaseApi
{
    public static function getMethods(): array
    {
        return [
            'controllers' => [
                'open' => [
                    'controller' => OpenController::class,
                    'actions' => [
                        'ping' => [
                            'action' => 'ping',
                            'method' => ['get', 'post'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
