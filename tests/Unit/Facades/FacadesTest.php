<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Facades\ApiModule;
use Dskripchenko\LaravelApi\Facades\ApiRequest;
use Dskripchenko\LaravelApi\Facades\ApiErrorHandler;

it('ApiModule facade accessor is api_module', function () {
    $ref = new \ReflectionMethod(ApiModule::class, 'getFacadeAccessor');
    $ref->setAccessible(true);
    expect($ref->invoke(null))->toBe('api_module');
});

it('ApiRequest facade accessor is api_request', function () {
    $ref = new \ReflectionMethod(ApiRequest::class, 'getFacadeAccessor');
    $ref->setAccessible(true);
    expect($ref->invoke(null))->toBe('api_request');
});

it('ApiErrorHandler facade accessor is api_error_handler', function () {
    $ref = new \ReflectionMethod(ApiErrorHandler::class, 'getFacadeAccessor');
    $ref->setAccessible(true);
    expect($ref->invoke(null))->toBe('api_error_handler');
});
