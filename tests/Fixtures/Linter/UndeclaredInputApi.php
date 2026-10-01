<?php

declare(strict_types=1);

namespace Tests\Fixtures\Linter;

use Dskripchenko\LaravelApi\Components\BaseApi;

/**
 * Undeclared Input API
 *
 * Actions that validate fields the markup never mentions — and the ones that
 * must not be reported.
 */
class UndeclaredInputApi extends BaseApi
{
    public static function getMethods(): array
    {
        return [
            'controllers' => [
                'things' => [
                    'controller' => UndeclaredInputController::class,
                    'actions' => [
                        'validatesLiteral' => ['method' => ['post']],
                        'validatesRuntime' => ['method' => ['post']],
                        'validatorFacade' => ['method' => ['post']],
                        'validatorHelper' => ['method' => ['post']],
                        'formRequest' => ['method' => ['post']],
                        'declared' => ['method' => ['post']],
                        'declaredDynamically' => ['method' => ['post']],
                        'takesNothing' => ['method' => ['get']],
                        'onlyMentionsInComment' => ['method' => ['get']],
                    ],
                ],
            ],
        ];
    }
}
