<?php

declare(strict_types=1);

namespace Tests\Fixtures\Linter;

use Dskripchenko\LaravelApi\Components\BaseModule;

class WarningOnlyModule extends BaseModule
{
    public function getApiVersionList(): array
    {
        return [
            'v1' => WarningOnlyApi::class,
        ];
    }
}
