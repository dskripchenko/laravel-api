<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Components\BaseApi;
use Tests\Fixtures\TestModule;

beforeEach(function () {
    $ref = new ReflectionProperty(BaseApi::class, 'preparedMethods');
    $ref->setValue(null, []);
});

it('returns route definitions for all versions', function () {
    $module = new TestModule();
    $definitions = $module->getRouteDefinitions();

    expect($definitions)->toBeArray();
    expect($definitions)->not->toBeEmpty();

    $names = array_column($definitions, 'name');
    expect($names)->toContain('api.v1.item.list');
    expect($names)->toContain('api.v1.item.show');
    expect($names)->toContain('api.v1.item.create');
    expect($names)->toContain('api.v1.open.ping');
    expect($names)->toContain('api.v2.item.list');
    expect($names)->toContain('api.v2.item.search');
});

it('excludes disabled actions', function () {
    $module = new TestModule();
    $definitions = $module->getRouteDefinitions();

    $names = array_column($definitions, 'name');
    expect($names)->not->toContain('api.v1.item.disabled');
    expect($names)->not->toContain('api.v2.item.remove');
});

it('includes correct HTTP methods', function () {
    $module = new TestModule();
    $definitions = $module->getRouteDefinitions();

    $listRoute = collect($definitions)->firstWhere('name', 'api.v1.item.list');
    expect($listRoute['methods'])->toBe(['get']);

    $createRoute = collect($definitions)->firstWhere('name', 'api.v1.item.create');
    expect($createRoute['methods'])->toBe(['post']);
});

it('generates correct URI', function () {
    $module = new TestModule();
    $definitions = $module->getRouteDefinitions();

    $listRoute = collect($definitions)->firstWhere('name', 'api.v1.item.list');
    expect($listRoute['uri'])->toBe('v1/item/list');

    $searchRoute = collect($definitions)->firstWhere('name', 'api.v2.item.search');
    expect($searchRoute['uri'])->toBe('v2/item/search');
});

it('defaults HTTP method to post', function () {
    $module = new TestModule();
    $definitions = $module->getRouteDefinitions();

    $updateRoute = collect($definitions)->firstWhere('name', 'api.v1.item.update');
    expect($updateRoute['methods'])->toBe(['post']);
});

it('includes aliased actions with action key as name', function () {
    $module = new TestModule();
    $definitions = $module->getRouteDefinitions();

    $removeRoute = collect($definitions)->firstWhere('name', 'api.v1.item.remove');
    expect($removeRoute)->not->toBeNull();
    expect($removeRoute['uri'])->toBe('v1/item/remove');
});

it('uses custom name from action config', function () {
    $apiClass = new class extends \Dskripchenko\LaravelApi\Components\BaseApi {
        public static function getMethods(): array
        {
            return [
                'controllers' => [
                    'user' => [
                        'controller' => \Tests\Fixtures\Versions\v1\Controllers\ItemController::class,
                        'actions' => [
                            'list' => [
                                'action' => 'list',
                                'method' => ['get'],
                                'name' => 'users.index',
                            ],
                            'show' => [
                                'action' => 'show',
                                'method' => ['get'],
                            ],
                        ],
                    ],
                ],
            ];
        }
    };

    $module = new class extends \Dskripchenko\LaravelApi\Components\BaseModule {
        public $apiClass;

        public function getApiVersionList(): array
        {
            return ['v1' => $this->apiClass];
        }
    };
    $module->apiClass = $apiClass::class;

    $definitions = $module->getRouteDefinitions();

    $listRoute = collect($definitions)->firstWhere('action', 'list');
    expect($listRoute['name'])->toBe('api.v1.users.index');

    $showRoute = collect($definitions)->firstWhere('action', 'show');
    expect($showRoute['name'])->toBe('api.v1.user.show');
});
