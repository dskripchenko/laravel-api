<?php

declare(strict_types=1);

namespace Tests\Fixtures\Linter;

use Dskripchenko\LaravelApi\Components\BaseApi;

/**
 * Warning-only API
 *
 * Nothing here is broken enough to fail a build — which is exactly what
 * --strict is for.
 */
class WarningOnlyApi extends BaseApi
{
    public static function getMethods(): array
    {
        return [
            'controllers' => [
                'soft' => [
                    'controller' => WarningOnlyController::class,
                    'actions' => [
                        'unknownType' => ['action' => 'unknownType'],
                    ],
                ],
            ],
        ];
    }
}
