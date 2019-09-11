<?php

namespace Dskripchenko\LaravelApiExample;


class ExampleModule extends \Dskripchenko\LaravelApi\Components\BaseModule
{
    public function getApiVersionList()
    {
        return [
            'v1' => \Dskripchenko\LaravelApiExample\Versions\v1\Api::class,
            'v1.1' => \Dskripchenko\LaravelApiExample\Versions\v1_1\Api::class,
            'v1.2' => \Dskripchenko\LaravelApiExample\Versions\v1_2\Api::class,
            'v2' => \Dskripchenko\LaravelApiExample\Versions\v2\Api::class,
        ];
    }
}