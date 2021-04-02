<?php

namespace Components\LaravelApiExample;

use Components\LaravelApi\Components\BaseModule;
use Components\LaravelApiExample\Versions\v1\Api as V1;
use Components\LaravelApiExample\Versions\v1_1\Api as V1_1;
use Components\LaravelApiExample\Versions\v1_2\Api as V1_2;
use Components\LaravelApiExample\Versions\v2\Api as V2;

/**
 * Class ExampleModule
 * @package Components\LaravelApiExample
 */
class ExampleModule extends BaseModule
{
    public function getApiVersionList(): array
    {
        return [
            'v1' => V1::class,
            'v1.1' => V1_1::class,
            'v1.2' => V1_2::class,
            'v2' => V2::class,
        ];
    }
}
