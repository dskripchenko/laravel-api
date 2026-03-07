<?php

namespace Dskripchenko\LaravelApiExample;

use Dskripchenko\LaravelApi\Components\BaseModule;
use Dskripchenko\LaravelApiExample\Versions\v1\Api as V1;
use Dskripchenko\LaravelApiExample\Versions\v1_1\Api as V1_1;
use Dskripchenko\LaravelApiExample\Versions\v1_2\Api as V1_2;
use Dskripchenko\LaravelApiExample\Versions\v2\Api as V2;

/**
 * Class ExampleModule
 * @package Dskripchenko\LaravelApiExample
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
