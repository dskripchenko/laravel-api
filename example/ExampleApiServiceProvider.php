<?php
namespace Dskripchenko\LaravelApiExample;

use Dskripchenko\LaravelApi\Providers\ApiServiceProvider;

/**
 * Class ExampleApiServiceProvider
 * @package Dskripchenko\LaravelApiExample
 */
class ExampleApiServiceProvider extends ApiServiceProvider
{
    protected function getApiModule(): ExampleModule
    {
        return new ExampleModule();
    }
}
