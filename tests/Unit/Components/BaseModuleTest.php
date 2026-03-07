<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Components\BaseModule;
use Dskripchenko\LaravelApi\Facades\ApiModule;
use Tests\Fixtures\TestModule;
use Tests\Fixtures\Versions\v1\TestApi;

it('returns null when no version is provided and no request', function () {
    $module = new BaseModule();
    expect($module->getApi())->toBeNull();
});

it('returns api class for valid version', function () {
    $module = new TestModule();
    $api = $module->getApi('v1');
    expect($api)->toBe(TestApi::class);
});

it('returns null for invalid version', function () {
    $module = new TestModule();
    expect($module->getApi('v99'))->toBeNull();
});

it('returns null for non-BaseApi class', function () {
    ApiModule::shouldReceive('getApiVersionList')
        ->andReturn(['v1' => \stdClass::class]);

    $module = new BaseModule();
    expect($module->getApi('v1'))->toBeNull();
});

it('throws NotFoundHttpException when no api resolved in makeApi', function () {
    $module = new BaseModule();
    $module->makeApi();
})->throws(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

it('returns api prefix', function () {
    $module = new BaseModule();
    expect($module->getApiPrefix())->toBe('api');
});

it('returns api uri pattern', function () {
    $module = new BaseModule();
    expect($module->getApiUriPattern())->toBe('{version}/{controller}/{action}');
});

it('returns available api methods', function () {
    $module = new BaseModule();
    $methods = $module->getAvailableApiMethods();
    expect($methods)->toContain('get', 'post', 'put', 'patch', 'delete');
});
