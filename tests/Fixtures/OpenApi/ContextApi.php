<?php

declare(strict_types=1);

namespace Tests\Fixtures\OpenApi;

use Dskripchenko\LaravelApi\Components\BaseApi;

/**
 * Context API
 * One generic controller serving several entities, each with its own fields
 */
class ContextApi extends BaseApi
{
    public static $useResponseTemplates = true;

    public static function getMethods(): array
    {
        $entity = static fn (): array => [
            'controller' => ContextController::class,
            'actions' => [
                'create' => ['method' => ['post']],
                'update' => ['method' => ['post']],
                'search' => ['method' => ['get']],
                'show' => ['method' => ['get']],
                'legacy' => ['method' => ['post']],
                'scalars' => ['method' => ['post'], 'flavour' => 'spicy'],
            ],
        ];

        return [
            'controllers' => [
                'users' => $entity(),
                'posts' => $entity(),
            ],
        ];
    }
}
