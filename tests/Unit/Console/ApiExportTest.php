<?php

declare(strict_types=1);

it('has correct signature', function () {
    $command = new \Dskripchenko\LaravelApi\Console\Commands\ApiExport();
    expect($command->getName())->toBe('api:export');
});

it('is registered as artisan command', function () {
    $commands = \Illuminate\Support\Facades\Artisan::all();
    expect($commands)->toHaveKey('api:export');
});
