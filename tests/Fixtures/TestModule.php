<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Dskripchenko\LaravelApi\Components\BaseModule;
use Tests\Fixtures\Versions\v1\TestApi as V1;
use Tests\Fixtures\Versions\v2\TestApi as V2;

class TestModule extends BaseModule
{
    public function getApiVersionList(): array
    {
        return [
            'v1' => V1::class,
            'v2' => V2::class,
        ];
    }
}
