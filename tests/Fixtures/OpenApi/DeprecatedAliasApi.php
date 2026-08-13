<?php

declare(strict_types=1);

namespace Tests\Fixtures\OpenApi;

use Dskripchenko\LaravelApi\Components\BaseApi;
use Tests\Fixtures\Versions\v1\Controllers\OpenController;

/**
 * Legacy alias API
 * A deprecated alias: the same controllers, marked deprecated at the version level.
 */
class DeprecatedAliasApi extends BaseApi
{
    public static function getMethods(): array
    {
        return [
            'deprecated' => true,
            'controllers' => [
                'open' => [
                    'controller' => OpenController::class,
                    'actions' => [
                        'ping' => [
                            'action' => 'ping',
                            'method' => ['get'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
