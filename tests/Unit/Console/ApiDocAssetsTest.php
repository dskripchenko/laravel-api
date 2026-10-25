<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

it('downloads the configured documentation script into public/', function () {
    Http::fake(['cdn.example.com/*' => Http::response('window.DskOpenApi = {}', 200, ['Content-Type' => 'application/javascript; charset=utf-8'])]);
    config()->set('laravel-api.documentation.script', 'https://cdn.example.com/openapi/standalone.js');
    $target = public_path('vendor/laravel-api/openapi/standalone.js');
    File::delete($target);

    $this->artisan('api:doc-assets')
        ->expectsOutputToContain('LARAVEL_API_DOC_SCRIPT=/vendor/laravel-api/openapi/standalone.js')
        ->assertSuccessful();

    expect(File::get($target))->toBe('window.DskOpenApi = {}');
    File::deleteDirectory(public_path('vendor/laravel-api'));
});

it('refuses a script that is already local', function () {
    config()->set('laravel-api.documentation.script', '/vendor/laravel-api/openapi/standalone.js');

    $this->artisan('api:doc-assets')->assertFailed();
});

it('fails on an answer that is not a script', function () {
    Http::fake(['*' => Http::response('<html>Not found</html>', 404, ['Content-Type' => 'text/html'])]);

    $this->artisan('api:doc-assets', ['--from' => 'https://cdn.example.com/missing.js'])->assertFailed();
});
