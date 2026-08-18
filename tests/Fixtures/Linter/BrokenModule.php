<?php

declare(strict_types=1);

namespace Tests\Fixtures\Linter;

use Dskripchenko\LaravelApi\Components\BaseModule;

/**
 * A module that exposes the deliberately broken API, so the command can be
 * exercised end to end — the linter's own unit tests bypass the module.
 */
class BrokenModule extends BaseModule
{
    public function getApiVersionList(): array
    {
        return [
            'v1' => BrokenApi::class,
        ];
    }
}
