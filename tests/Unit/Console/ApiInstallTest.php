<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Console\Commands\ApiInstall;

it('has correct signature', function () {
    $command = new ApiInstall();
    $ref = new \ReflectionProperty($command, 'signature');
    $ref->setAccessible(true);
    expect($ref->getValue($command))->toBe('api:install');
});

it('getEnvConfig returns expected structure', function () {
    $command = new ApiInstall();
    $ref = new \ReflectionMethod($command, 'getEnvConfig');
    $ref->setAccessible(true);
    $config = $ref->invoke($command);

    expect($config)->toBeArray();
    expect($config)->not->toBeEmpty();
    // Should have database section
    $firstSection = array_values($config)[0];
    expect($firstSection)->toHaveKey('{{DB_CONNECTION}}');
    expect($firstSection)->toHaveKey('{{DB_HOST}}');
    expect($firstSection)->toHaveKey('{{DB_DATABASE}}');
});

it('is registered as artisan command', function () {
    $commands = \Illuminate\Support\Facades\Artisan::all();
    expect($commands)->toHaveKey('api:install');
});
