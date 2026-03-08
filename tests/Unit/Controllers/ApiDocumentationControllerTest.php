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
