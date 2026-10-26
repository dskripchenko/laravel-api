<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApi\Operations\Operation;
use Dskripchenko\LaravelApi\Operations\OperationRegistry;
use Tests\Fixtures\Middleware\TestAuthMiddleware;
use Tests\Fixtures\Middleware\TestLogMiddleware;
use Tests\Fixtures\Versions\v1\Controllers\OpenController;
use Tests\Fixtures\Versions\v1\TestApi as V1Api;
use Tests\Fixtures\Versions\v2\TestApi as V2Api;

/** An API class declaring exactly the given methods, without parents. */
function registryApi(array $methods): string
{
    $api = new class extends BaseApi {
        public static array $declared = [];

        public static function getMethods(): array
        {
            return static::$declared;
        }
    };
    $api::$declared = $methods;

    // One anonymous class serves every call: forget what it prepared before.
    (new ReflectionProperty(BaseApi::class, 'preparedMethods'))->setValue(null, []);

    return get_class($api);
}

function openController(array $actions, array $extra = []): array
{
    return ['controllers' => ['open' => array_merge(['controller' => OpenController::class, 'actions' => $actions], $extra)]];
}

it('reads every form of an action entry', function () {
    $api = registryApi(openController([
        'ping',
        'alias' => 'ping',
        'explicit' => ['action' => 'ping', 'method' => ['get']],
        'keyed' => [],
        'restored' => true,
        'disabled' => false,
    ]));

    $operations = $api::operations()->operations();
    $methods = array_map(static fn (Operation $o): string => "{$o->actionKey}:{$o->controllerMethod}", $operations);

    // Normalization moves list entries after the keyed ones.
    expect($methods)->toBe(['alias:ping', 'explicit:ping', 'keyed:keyed', 'restored:restored', 'ping:ping']);
});

it('defaults the HTTP methods to post', function () {
    $api = registryApi(openController([
        'ping',
        'empty' => ['action' => 'ping', 'method' => []],
        'scalar' => ['action' => 'ping', 'method' => 'get'],
        'many' => ['action' => 'ping', 'method' => ['get', 'post']],
    ]));
    $registry = $api::operations();

    expect($registry->find('open', 'ping')->httpMethods)->toBe(['post']);
    expect($registry->find('open', 'empty')->httpMethods)->toBe(['post']);
    expect($registry->find('open', 'scalar')->httpMethods)->toBe(['get']);
    expect($registry->find('open', 'many')->httpMethods)->toBe(['get', 'post']);
});

it('cascades the deprecated flag from the version and the controller', function () {
    $own = registryApi(openController(['ping', 'old' => ['action' => 'ping', 'deprecated' => true]]));
    expect($own::operations()->find('open', 'ping')->deprecated)->toBeFalse();
    expect($own::operations()->find('open', 'old')->deprecated)->toBeTrue();

    $controller = registryApi(openController(['ping'], ['deprecated' => true]));
    expect($controller::operations()->find('open', 'ping')->deprecated)->toBeTrue();

    $version = registryApi(['deprecated' => true] + openController(['ping']));
    expect($version::operations()->find('open', 'ping')->deprecated)->toBeTrue();
});

it('keeps the name, the security and the options as written', function () {
    $options = ['action' => 'ping', 'name' => 'health', 'security' => [['BearerAuth' => []]], 'flavour' => 'spicy'];
    $operation = registryApi(openController(['check' => $options]))::operations()->find('open', 'check');

    expect($operation->name)->toBe('health');
    expect($operation->security)->toBe([['BearerAuth' => []]]);
    expect($operation->options)->toBe($options);
    expect(registryApi(openController(['ping']))::operations()->find('open', 'ping')->name)->toBeNull();
});

it('keeps a controller entry without a usable class, and says it cannot be called', function () {
    $api = registryApi(['controllers' => [
        'nowhere' => ['actions' => ['ping']],
        'wrong' => ['controller' => 'No\\Such\\Controller', 'actions' => ['ping']],
        'open' => ['controller' => OpenController::class, 'actions' => ['ping', 'missing']],
    ]]);
    $registry = $api::operations();

    expect($registry->controllers()['nowhere']->class)->toBeNull();
    expect($registry->find('nowhere', 'ping')->isCallable())->toBeFalse();
    expect($registry->find('wrong', 'ping')->isCallable())->toBeFalse();
    expect($registry->find('open', 'missing')->isCallable())->toBeFalse();
    expect($registry->find('open', 'ping')->isCallable())->toBeTrue();
    expect($registry->find('open', 'ping')->callable())->toBe(OpenController::class . '@ping');
});

it('finds nothing for an unknown or disabled action', function () {
    $registry = registryApi(openController(['ping', 'off' => false]))::operations();

    expect($registry->find('open', 'off'))->toBeNull();
    expect($registry->find('open', 'nope'))->toBeNull();
    expect($registry->find('nope', 'ping'))->toBeNull();
    expect($registry->find(null, null))->toBeNull();
});

it('includes what a version inherits and leaves out what it disables', function () {
    $v2 = V2Api::operations('v2');

    expect($v2->find('item', 'list'))->not->toBeNull();
    expect($v2->find('item', 'remove'))->toBeNull();
    expect($v2->find('item', 'list')->version)->toBe('v2');
    expect($v2->find('item', 'list')->apiClass)->toBe(V2Api::class);
});

it('asks the API class for the middleware cascade', function () {
    $operation = V1Api::operations()->find('item', 'list');

    expect($operation->middleware())->toBe(V1Api::getMiddlewareByControllerAndActionKey('item', 'list'));
    expect($operation->middleware())->toContain(TestLogMiddleware::class, TestAuthMiddleware::class);
    expect($operation->excludedMiddleware())->toBe(V1Api::getExcludedMiddlewareByControllerAndActionKey('item', 'list'));
});

it('builds a registry once per prepared methods', function () {
    $first = OperationRegistry::for(V1Api::class, 'v1');

    expect(OperationRegistry::for(V1Api::class, 'v1'))->toBe($first);
    expect(OperationRegistry::for(V1Api::class, 'other'))->not->toBe($first);

    // Whoever resets BaseApi's cache gets a registry of what is there now.
    $ref = new ReflectionProperty(BaseApi::class, 'preparedMethods');
    $ref->setValue(null, [V1Api::class => openController(['ping'])]);

    $rebuilt = OperationRegistry::for(V1Api::class, 'v1');
    expect($rebuilt)->not->toBe($first);
    expect(array_keys($rebuilt->controllers()))->toBe(['open']);
});
