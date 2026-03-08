<?php

declare(strict_types=1);

namespace Tests;

use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApi\Requests\BaseApiRequest;
use Dskripchenko\LaravelApi\Traits\Testing\MakesHttpApiRequests;
use Tests\Fixtures\Versions\v1\TestApi;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use MakesHttpApiRequests;

    protected function setUp(): void
    {
        $this->resetStaticState();

        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            \Tests\Fixtures\TestServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function resetStaticState(): void
    {
        // Reset BaseApi::$preparedMethods
        $ref = new \ReflectionProperty(BaseApi::class, 'preparedMethods');
        $ref->setValue(null, []);

        // Reset BaseApiRequest::$_instance
        $ref = new \ReflectionProperty(BaseApiRequest::class, '_instance');
        $ref->setValue(null, null);

        // Reset OpenApiTrait caches (access via a class that uses the trait)
        $ref = new \ReflectionProperty(TestApi::class, 'docBlockFactory');
        $ref->setValue(null, null);

        $ref = new \ReflectionProperty(TestApi::class, 'cachedRawTemplates');
        $ref->setValue(null, null);

        $ref = new \ReflectionProperty(TestApi::class, 'middlewareInputTagCache');
        $ref->setValue(null, []);
    }
}
