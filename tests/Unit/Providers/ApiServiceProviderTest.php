<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Components\BaseModule;
use Dskripchenko\LaravelApi\Exceptions\ApiErrorHandler;
use Dskripchenko\LaravelApi\Exceptions\Handler;
use Illuminate\Contracts\Debug\ExceptionHandler;

it('binds api_module to container', function () {
    expect(app('api_module'))->toBeInstanceOf(BaseModule::class);
});

it('binds api_error_handler to container', function () {
    expect(app('api_error_handler'))->toBeInstanceOf(ApiErrorHandler::class);
});

it('binds api_request to container', function () {
    $request = app('api_request');
    // Should return BaseApiRequest instance or null
    expect($request)->not->toBeNull();
});

it('replaces ExceptionHandler with package Handler', function () {
    expect(app(ExceptionHandler::class))->toBeInstanceOf(Handler::class);
});

it('registers api:install command', function () {
    $commands = \Illuminate\Support\Facades\Artisan::all();
    expect($commands)->toHaveKey('api:install');
});

it('registers api-endpoint route', function () {
    $routes = app('router')->getRoutes();
    $route = $routes->getByName('api-endpoint');
    expect($route)->not->toBeNull();
});

it('registers api-doc route', function () {
    $routes = app('router')->getRoutes();
    $route = $routes->getByName('api-doc');
    expect($route)->not->toBeNull();
});
