<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Controllers\ApiDocumentationController;
use Illuminate\Support\Facades\Storage;

it('creates swagger directory if not exists', function () {
    Storage::fake();

    $controller = new ApiDocumentationController();

    try {
        $controller->index();
    } catch (\Throwable $e) {
        // View rendering may fail in test env
    }

    Storage::assertExists('public/swagger');
});

it('generates JSON files per version', function () {
    Storage::fake();

    $controller = new ApiDocumentationController();

    try {
        $controller->index();
    } catch (\Throwable $e) {
        // View rendering may fail
    }

    Storage::assertExists('public/swagger/v1.json');
    Storage::assertExists('public/swagger/v2.json');
});

it('generated JSON is valid swagger', function () {
    Storage::fake();

    $controller = new ApiDocumentationController();

    try {
        $controller->index();
    } catch (\Throwable $e) {
        // View rendering may fail
    }

    $content = Storage::get('public/swagger/v1.json');
    $data = json_decode($content, true);
    expect($data['swagger'])->toBe('2.0');
    expect($data['info'])->toHaveKey('title');
    expect($data['paths'])->not->toBeEmpty();
});
