<?php
namespace Components\LaravelApiExample;

use Dskripchenko\LaravelApi\Providers\ApiServiceProvider;

/**
 * Class ExampleApiServiceProvider
 * @package Components\LaravelApiExample
 */
class ExampleApiServiceProvider extends ApiServiceProvider
{
    protected function getApiModule(): ExampleModule
    {
        return new ExampleModule();
    }
}
