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
        'filesData' => [['url' => '/openapi/v1.json', 'name' => 'V1']],
        'documentationScript' => (string) config('laravel-api.documentation_script'),
    ])->render();

    expect($html)->toContain('src="/vendor/scalar/api-reference.js"');
    expect($html)->not->toContain('cdn.jsdelivr.net');
    expect($html)->toContain('api-doc-fallback');
    // Список спек рендерится сервером — он не должен зависеть от JS.
    expect($html)->toContain('href="/openapi/v1.json"');
});

it('documentation view survives apostrophes and newlines in spec titles', function () {
    Storage::fake();

    // Раньше JSON клался в JS-строку в одинарных кавычках: апостроф ронял
    // скрипт синтаксической ошибкой, а перенос строки — сам JSON.parse.
    $html = view('api_module::api/documentation', [
        'filesData' => [[
            'url' => '/openapi/v1.json',
            'name' => "Admin API — описание endpoint'ов\nи caller's flow",
        ]],
        'documentationScript' => '/vendor/scalar/api-reference.js',
    ])->render();

    expect($html)->not->toContain("JSON.parse('");
    // Апостроф внутри JS-литерала обязан быть экранирован (@json → \u0027).
    expect($html)->toContain('\u0027');
    expect($html)->not->toMatch("/var sources = .*endpoint'ов/");
});

it('keeps hidden versions out of the reference index', function () {
    Storage::fake();
    config()->set('laravel-api.hidden_versions', ['v2']);

    $data = (new ApiDocumentationController())->index()->getData();
    $names = array_column($data['filesData'], 'url');

    expect(implode(' ', $names))->toContain('v1')->not->toContain('v2');
});

it('still serves a hidden version by direct URL', function () {
    Storage::fake();
    config()->set('laravel-api.hidden_versions', ['v2']);

    $response = (new ApiDocumentationController())->source('v2');

    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toHaveKey('openapi');
});
