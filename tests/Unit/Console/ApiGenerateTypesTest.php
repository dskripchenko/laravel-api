<?php

declare(strict_types=1);

it('has correct signature', function () {
    $command = new \Dskripchenko\LaravelApi\Console\Commands\ApiGenerateTypes();
    expect($command->getName())->toBe('api:generate-types');
});

it('is registered as artisan command', function () {
    $commands = \Illuminate\Support\Facades\Artisan::all();
    expect($commands)->toHaveKey('api:generate-types');
});
