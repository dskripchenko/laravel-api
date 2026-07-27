<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

it('is registered as artisan command', function () {
    expect(Artisan::all())->toHaveKey('api:doc-clear');
});

it('clears all cached spec files', function () {
    $folder = config('laravel-api.openapi_path', 'public/openapi');
    Storage::put("{$folder}/v1.json", '{}');
    Storage::put("{$folder}/v2.json", '{}');
    Storage::put("{$folder}/readme.txt", 'keep');

    $this->artisan('api:doc-clear')
        ->expectsOutputToContain('Cleared 2 cached OpenAPI spec(s).')
        ->assertSuccessful();

    expect(Storage::exists("{$folder}/v1.json"))->toBeFalse();
    expect(Storage::exists("{$folder}/v2.json"))->toBeFalse();
    expect(Storage::exists("{$folder}/readme.txt"))->toBeTrue();
});

it('clears a single version spec with --api-version', function () {
    $folder = config('laravel-api.openapi_path', 'public/openapi');
    Storage::put("{$folder}/v1.json", '{}');
    Storage::put("{$folder}/v2.json", '{}');

    $this->artisan('api:doc-clear', ['--api-version' => 'v1'])->assertSuccessful();

    expect(Storage::exists("{$folder}/v1.json"))->toBeFalse();
    expect(Storage::exists("{$folder}/v2.json"))->toBeTrue();
});

it('succeeds when there is nothing to clear', function () {
    Storage::deleteDirectory(config('laravel-api.openapi_path', 'public/openapi'));

    $this->artisan('api:doc-clear')
        ->expectsOutputToContain('Cleared 0 cached OpenAPI spec(s).')
        ->assertSuccessful();

    $this->artisan('api:doc-clear', ['--api-version' => 'absent'])
        ->expectsOutputToContain('Nothing to clear')
        ->assertSuccessful();
});

it('optimize:clear also clears cached specs (provider hook)', function () {
    if (! method_exists(Illuminate\Support\ServiceProvider::class, 'optimizes')) {
        $this->markTestSkipped('optimizes() requires Laravel 11+');
    }
    $folder = config('laravel-api.openapi_path', 'public/openapi');
    Storage::put("{$folder}/v1.json", '{}');

    $this->artisan('optimize:clear')->assertSuccessful();

    expect(Storage::exists("{$folder}/v1.json"))->toBeFalse();
});
