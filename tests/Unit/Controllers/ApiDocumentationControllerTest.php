<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Controllers\ApiDocumentationController;
use Illuminate\Support\Facades\Storage;

it('creates OpenAPI directory if not exists', function () {
    Storage::fake();

    $controller = new ApiDocumentationController();

    try {
        $controller->index();
    } catch (\Throwable $e) {
        // View rendering may fail in test env
    }

    Storage::assertExists('public/openapi');
});

it('generates JSON files per version', function () {
    Storage::fake();

    $controller = new ApiDocumentationController();

    try {
        $controller->index();
    } catch (\Throwable $e) {
        // View rendering may fail
    }

    Storage::assertExists('public/openapi/v1.json');
    Storage::assertExists('public/openapi/v2.json');
});

it('generated JSON is valid OpenAPI', function () {
    Storage::fake();

    $controller = new ApiDocumentationController();

    try {
        $controller->index();
    } catch (\Throwable $e) {
        // View rendering may fail
    }

    $content = Storage::get('public/openapi/v1.json');
    $data = json_decode($content, true);
    expect($data['openapi'])->toBe('3.0.0');
    expect($data['info'])->toHaveKey('title');
    expect($data['paths'])->not->toBeEmpty();
});

it('passes a configurable documentation script URL to the view', function () {
    Storage::fake();
    config()->set('laravel-api.documentation_script', '/vendor/scalar/api-reference.js');

    $controller = new ApiDocumentationController();
    $view = $controller->index();

    expect($view->getData()['documentationScript'])->toBe('/vendor/scalar/api-reference.js');
});

it('documentation view renders configured script and a fallback block', function () {
    Storage::fake();
    config()->set('laravel-api.documentation_script', '/vendor/scalar/api-reference.js');

    $html = view('api_module::api/documentation', [
        'filesJsonData' => json_encode([['url' => '/openapi/v1.json', 'name' => 'V1']]),
        'documentationScript' => (string) config('laravel-api.documentation_script'),
    ])->render();

    expect($html)->toContain('src="/vendor/scalar/api-reference.js"');
    expect($html)->not->toContain('cdn.jsdelivr.net');
    expect($html)->toContain('api-doc-fallback');
});
