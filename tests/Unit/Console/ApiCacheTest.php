<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApi\Components\BaseModule;
use Dskripchenko\LaravelApi\Facades\ApiModule;
use Dskripchenko\LaravelApi\Services\ApiCache;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Tests\Fixtures\Linter\BrokenModule;
use Tests\Fixtures\TestModule;
use Tests\Fixtures\TestServiceProvider;
use Tests\Fixtures\Versions\v1\Controllers\OpenController;
use Tests\Fixtures\Versions\v1\TestApi as V1;
use Tests\Fixtures\Versions\v2\TestApi as V2;

function swapModule(string $moduleClass): void
{
    app()->bind('api_module', static fn () => new $moduleClass());
    ApiModule::clearResolvedInstance('api_module');
}

afterEach(function () {
    ApiCache::clear();
});

it('registers the commands, and api:clear with optimize:clear', function () {
    expect(Artisan::all())->toHaveKeys(['api:cache', 'api:clear']);
    expect(ServiceProvider::$optimizeClearCommands)->toContain('api:clear');
    expect(ServiceProvider::$optimizeCommands)->not->toContain('api:cache');
});

it('caches the prepared methods and the route definitions', function () {
    $module = new TestModule();
    $routes = $module->getRouteDefinitions();
    $prepared = V2::getPreparedMethods();

    $this->artisan('api:cache', ['--no-docs' => true])
        ->expectsOutputToContain('API map cached: 2 version(s), ' . count($routes) . ' route(s).')
        ->assertSuccessful();

    expect(is_file(ApiCache::path()))->toBeTrue();
    expect(ApiCache::preparedMethods(V2::class))->toBe($prepared);
    expect(ApiCache::routeDefinitions(TestModule::class))->toBe($routes);
    expect(ApiCache::routeDefinitions(BrokenModule::class))->toBeNull();
});

it('serves the map from the cache once it is there', function () {
    $frozen = ['controllers' => ['frozen' => ['controller' => OpenController::class, 'actions' => ['ping' => ['action' => 'ping']]]]];
    $data = ApiCache::build(new TestModule());
    $data['prepared'][V1::class] = $frozen;
    $data['routes'] = [['frozen' => true]];
    ApiCache::write($data);
    BaseApi::flushPreparedMethods();

    expect(V1::getPreparedMethods())->toBe($frozen);
    expect(V1::operations()->find('frozen', 'ping'))->not->toBeNull();
    expect((new TestModule())->getRouteDefinitions())->toBe([['frozen' => true]]);
});

it('ignores a file of another format', function () {
    file_put_contents(ApiCache::path(), '<?php return ' . var_export(['format' => 0, 'prepared' => [V1::class => []]], true) . ';');
    ApiCache::flush();

    expect(ApiCache::exists())->toBeFalse();
    expect(ApiCache::preparedMethods(V1::class))->toBeNull();
});

it('builds the OpenAPI spec of every version', function () {
    $folder = config('laravel-api.openapi_path', 'public/openapi');
    Storage::deleteDirectory($folder);

    $this->artisan('api:cache')
        ->expectsOutputToContain('2 OpenAPI spec(s) built')
        ->assertSuccessful();

    expect(json_decode(Storage::get("{$folder}/v1.json"), true))->toBe(json_decode(json_encode(V1::getOpenApiConfig('v1')), true));
    expect(Storage::exists("{$folder}/v2.json"))->toBeTrue();
});

it('fails, and writes nothing, when a spec cannot be built', function () {
    swapModule(BrokenModule::class);

    $this->artisan('api:cache')
        ->expectsOutputToContain('The spec of v1 cannot be built')
        ->assertFailed();

    expect(is_file(ApiCache::path()))->toBeFalse();
});

it('fails, and writes nothing, when the map holds what a file cannot carry', function () {
    $api = new class extends BaseApi {
        public static function getMethods(): array
        {
            return ['controllers' => ['open' => [
                'controller' => OpenController::class,
                'actions' => ['ping' => ['action' => 'ping', 'resolver' => static fn () => 1]],
            ]]];
        }
    };
    $module = new class (get_class($api)) extends BaseModule {
        public function __construct(private string $api)
        {
        }

        public function getApiVersionList(): array
        {
            return ['v1' => $this->api];
        }
    };
    app()->instance('api_module', $module);
    ApiModule::clearResolvedInstance('api_module');

    $this->artisan('api:cache', ['--no-docs' => true])
        ->expectsOutputToContain('holds a Closure at `prepared.')
        ->assertFailed();

    expect(is_file(ApiCache::path()))->toBeFalse();
});

it('clears the cache', function () {
    $this->artisan('api:cache', ['--no-docs' => true])->assertSuccessful();

    $this->artisan('api:clear')->expectsOutputToContain('Cached API map cleared.')->assertSuccessful();
    expect(is_file(ApiCache::path()))->toBeFalse();

    $this->artisan('api:clear')->expectsOutputToContain('There was no cached API map.')->assertSuccessful();
});

it('does not register the routes again when they are cached, but keeps the middleware group', function () {
    $register = static function (): int {
        Route::setRoutes(new RouteCollection());
        Route::middlewareGroup('api-middleware-group', []);
        (new TestServiceProvider(app()))->makeApiRoutes();

        return count(Route::getRoutes()->getRoutes());
    };

    app()->instance('routes.cached', false);
    expect($register())->toBeGreaterThan(0);

    // What routesAreCached() remembers once it has looked for the file.
    app()->instance('routes.cached', true);
    expect($register())->toBe(0);
    expect(Route::getMiddlewareGroups()['api-middleware-group'])->toBe(ApiModule::getApiMiddleware());
});
