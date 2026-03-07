<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApi\Facades\ApiRequest;
use Tests\Fixtures\Versions\v1\TestApi as V1Api;
use Tests\Fixtures\Versions\v2\TestApi as V2Api;
use Tests\Fixtures\Middleware\TestAuthMiddleware;
use Tests\Fixtures\Middleware\TestLogMiddleware;

it('returns empty methods by default', function () {
    $api = new class extends BaseApi {};
    expect($api::getMethods())->toBe([]);
});

it('throws NotFoundHttpException for non-existent action', function () {
    // Mock ApiRequest to return a non-existent controller/action
    ApiRequest::shouldReceive('getApiControllerKey')->andReturn('nonexistent');
    ApiRequest::shouldReceive('getApiActionKey')->andReturn('missing');
    ApiRequest::shouldReceive('method')->andReturn('post');

    V1Api::make();
})->throws(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

it('throws NotFoundHttpException for unsupported HTTP method', function () {
    ApiRequest::shouldReceive('getApiControllerKey')->andReturn('item');
    ApiRequest::shouldReceive('getApiActionKey')->andReturn('list');
    ApiRequest::shouldReceive('method')->andReturn('delete'); // list only supports GET

    V1Api::make();
})->throws(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class, 'method is not supported');

it('throws NotFoundHttpException for disabled action', function () {
    ApiRequest::shouldReceive('getApiControllerKey')->andReturn('item');
    ApiRequest::shouldReceive('getApiActionKey')->andReturn('disabled');
    ApiRequest::shouldReceive('method')->andReturn('post');

    V1Api::make();
})->throws(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

it('returns action method for controller and action', function () {
    $method = V1Api::getActionMethod('item', 'list');
    expect($method)->toBe('get');
});

it('defaults action method to post', function () {
    $method = V1Api::getActionMethod('item', 'nonexistent');
    expect($method)->toBe('post');
});

it('returns default empty response as array', function () {
    $api = new class extends BaseApi {};
    expect($api::getDefaultEmptyResponse())->toBe([]);
});

it('returns empty swagger templates by default', function () {
    $api = new class extends BaseApi {};
    expect($api::getSwaggerTemplates())->toBe([]);
});

// getPreparedMethods tests
it('merges parent and child methods through inheritance', function () {
    $methods = callPrivateStaticMethod(V2Api::class, 'getPreparedMethods');

    // V2 inherits from V1 - should have 'open' controller from V1
    expect($methods['controllers'])->toHaveKey('open');
    // Should have 'item' controller with merged actions
    expect($methods['controllers'])->toHaveKey('item');
});

it('v2 inherits v1 actions', function () {
    $methods = callPrivateStaticMethod(V2Api::class, 'getPreparedMethods');
    // 'list' comes from v1
    expect($methods['controllers']['item']['actions'])->toHaveKey('list');
});

it('v2 adds new actions', function () {
    $methods = callPrivateStaticMethod(V2Api::class, 'getPreparedMethods');
    expect($methods['controllers']['item']['actions'])->toHaveKey('search');
});

it('v2 can disable inherited actions', function () {
    $methods = callPrivateStaticMethod(V2Api::class, 'getPreparedMethods');
    // 'remove' is set to false in v2
    expect($methods['controllers']['item']['actions']['remove'])->toBeFalse();
});

it('caches prepared methods per class', function () {
    $methods1 = callPrivateStaticMethod(V1Api::class, 'getPreparedMethods');
    $methods2 = callPrivateStaticMethod(V1Api::class, 'getPreparedMethods');
    expect($methods1)->toBe($methods2);
});

// getMiddleware tests
it('returns empty middleware for invalid action', function () {
    ApiRequest::shouldReceive('getApiControllerKey')->andReturn('nonexistent');
    ApiRequest::shouldReceive('getApiActionKey')->andReturn('missing');

    expect(V1Api::getMiddleware())->toBe([]);
});

it('merges global and controller middleware', function () {
    ApiRequest::shouldReceive('getApiControllerKey')->andReturn('item');
    ApiRequest::shouldReceive('getApiActionKey')->andReturn('list');

    $middleware = V1Api::getMiddleware();
    expect($middleware)->toContain(TestLogMiddleware::class);
    expect($middleware)->toContain(TestAuthMiddleware::class);
});

it('includes only global middleware for controller without middleware', function () {
    ApiRequest::shouldReceive('getApiControllerKey')->andReturn('open');
    ApiRequest::shouldReceive('getApiActionKey')->andReturn('ping');

    $middleware = V1Api::getMiddleware();
    expect($middleware)->toContain(TestLogMiddleware::class);
    expect($middleware)->not->toContain(TestAuthMiddleware::class);
});

// normalizedMethods tests
it('normalizes numeric action keys to named keys', function () {
    $api = new class extends BaseApi {
        public static function getMethods(): array
        {
            return [
                'controllers' => [
                    'test' => [
                        'controller' => \Tests\Fixtures\Versions\v1\Controllers\OpenController::class,
                        'actions' => ['ping'],
                    ],
                ],
            ];
        }
    };
    $methods = callPrivateStaticMethod(get_class($api), 'getPreparedMethods');
    expect($methods['controllers']['test']['actions'])->toHaveKey('ping');
    expect($methods['controllers']['test']['actions']['ping']['action'])->toBe('ping');
});

it('normalizes string action values', function () {
    $methods = callPrivateStaticMethod(V1Api::class, 'getPreparedMethods');
    // 'remove' => 'delete' normalized to 'remove' => ['action' => 'delete']
    // But in V1, we check it before v2 disables it
    // Let's check v1 directly
    $v1Methods = callPrivateStaticMethod(V1Api::class, 'getNormalizedMethods');
    expect($v1Methods['controllers']['item']['actions']['remove']['action'])->toBe('delete');
});

// Helper function
function callPrivateStaticMethod(string $class, string $method, array $args = [])
{
    $ref = new \ReflectionMethod($class, $method);
    $ref->setAccessible(true);
    return $ref->invoke(null, ...$args);
}
