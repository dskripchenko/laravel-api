<?php

declare(strict_types=1);

it('config file exists and returns array', function () {
    $config = require dirname(__DIR__, 3) . '/config/laravel-api.php';
    expect($config)->toBeArray();
});

it('has expected config keys', function () {
    $config = require dirname(__DIR__, 3) . '/config/laravel-api.php';
    expect($config)->toHaveKey('prefix');
    expect($config)->toHaveKey('uri_pattern');
    expect($config)->toHaveKey('available_methods');
    expect($config)->toHaveKey('swagger_path');
    expect($config)->toHaveKey('doc_middleware');
});

it('has correct default values', function () {
    $config = require dirname(__DIR__, 3) . '/config/laravel-api.php';
    expect($config['prefix'])->toBe('api');
    expect($config['uri_pattern'])->toBe('{version}/{controller}/{action}');
    expect($config['available_methods'])->toBe(['get', 'post', 'put', 'patch', 'delete']);
});

it('BaseModule reads config values', function () {
    config()->set('laravel-api.prefix', 'custom-api');
    $module = new \Dskripchenko\LaravelApi\Components\BaseModule();
    expect($module->getApiPrefix())->toBe('custom-api');
});
