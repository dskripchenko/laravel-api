<?php
namespace Dskripchenko\LaravelApiExample;

use Dskripchenko\LaravelApi\ApiServiceProvider;

class ExampleApiServiceProvider extends ApiServiceProvider
{
    protected function getApiModule()
    {
        return new ExampleModule();
    }
}