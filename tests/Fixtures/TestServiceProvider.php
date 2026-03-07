<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Dskripchenko\LaravelApi\Providers\ApiServiceProvider;

class TestServiceProvider extends ApiServiceProvider
{
    protected function getApiModule(): TestModule
    {
        return new TestModule();
    }
}
