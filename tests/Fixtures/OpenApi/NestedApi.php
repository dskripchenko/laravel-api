<?php

declare(strict_types=1);

namespace Tests\Fixtures\OpenApi;

use Dskripchenko\LaravelApi\Components\BaseApi;

/**
 * Nested API
 * Dot- and bracket-notation corner cases
 */
class NestedApi extends BaseApi
{
    public static function getMethods(): array
    {
        return [
            'controllers' => [
                'nested' => [
                    'controller' => NestedController::class,
                    'actions' => [
                        'save' => ['method' => ['post']],
                        'show' => ['method' => ['get']],
                    ],
                ],
            ],
        ];
    }
}
