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
    config()->set('laravel-api.documentation.script', '/vendor/laravel-api/openapi/standalone.js');

    $view = (new ApiDocumentationController())->index();

    expect($view->getData()['documentationScript'])->toBe('/vendor/laravel-api/openapi/standalone.js');
});

it('defaults to the pinned @dskripchenko/openapi script', function () {
    Storage::fake();
    config()->set('laravel-api.documentation.script', null);

    $view = (new ApiDocumentationController())->index();

    expect($view->getData()['documentationScript'])->toContain('@dskripchenko/openapi@')->toContain('/dist/standalone.js');
});

it('hands the versions to the page as sources with DocLink slugs and the page settings', function () {
    Storage::fake();
    config()->set('laravel-api.documentation', [
        'locale' => 'ru',
        'theme' => 'dark',
        'try_it' => 'false',
        'proxy' => '/api/doc-proxy',
        'persist_credentials' => 'local',
        'snippet_targets' => ['curl', 'php-laravel'],
        'default_snippet' => 'php-laravel',
        'title' => 'Shop API',
    ]);

    $options = (new ApiDocumentationController())->index()->getData()['documentationOptions'];

    expect($options['sources'][0])->toHaveKeys(['url', 'title', 'slug']);
    expect(array_column($options['sources'], 'slug'))->toContain('v1');
    expect($options)->toMatchArray([
        'locale' => 'ru',
        'theme' => 'dark',
        'tryIt' => false,
        'proxy' => '/api/doc-proxy',
        'persistCredentials' => 'local',
        'snippetTargets' => ['curl', 'php-laravel'],
        'defaultSnippet' => 'php-laravel',
        'title' => 'Shop API',
    ]);
});

it('documentation view mounts the reference and keeps a fallback block', function () {
    Storage::fake();

    $html = view('api_module::api/documentation', [
        'filesData' => [['url' => '/openapi/v1.json', 'name' => "The caller's API", 'slug' => 'v1']],
        'documentationScript' => '/vendor/laravel-api/openapi/standalone.js',
        'documentationOptions' => ['sources' => [['url' => '/openapi/v1.json', 'title' => "The caller's API", 'slug' => 'v1']]],
    ])->render();

    expect($html)->toContain('src="/vendor/laravel-api/openapi/standalone.js"');
    expect($html)->toContain('DskOpenApi.createApiReference');
    expect($html)->not->toContain('Scalar');
    expect($html)->toContain('api-doc-fallback');
    // The list of specs is rendered by the server — it must not depend on JS.
    expect($html)->toContain('href="/openapi/v1.json"');
    // An apostrophe in a title cannot break the script.
    expect($html)->toContain('caller' . '\\' . 'u0027s');
});

it('documentation view survives apostrophes and newlines in spec titles', function () {
    Storage::fake();

    // The JSON used to be put into a JS string in single quotes: an apostrophe
    // brought the script down with a syntax error and a line break brought down
    // JSON.parse itself.
    $title = "Admin API — описание endpoint'ов\nи caller's flow";
    $html = view('api_module::api/documentation', [
        'filesData' => [['url' => '/openapi/v1.json', 'name' => $title, 'slug' => 'v1']],
        'documentationScript' => '/vendor/laravel-api/openapi/standalone.js',
        'documentationOptions' => ['sources' => [['url' => '/openapi/v1.json', 'title' => $title, 'slug' => 'v1']]],
    ])->render();

    expect($html)->not->toContain("JSON.parse('");
    // An apostrophe inside a JS literal must be escaped (@json → \u0027).
    expect($html)->toContain('\u0027');
    expect($html)->not->toMatch("/var options = .*endpoint'ов/");
});

it('keeps hidden versions out of the reference index', function () {
    Storage::fake();
    config()->set('laravel-api.hidden_versions', ['v2']);

    $data = (new ApiDocumentationController())->index()->getData();
    $names = array_column($data['filesData'], 'url');

    expect(implode(' ', $names))->toContain('v1')->not->toContain('v2');
});

it('hands the page a slug per version, so deep links do not hang off prose', function () {
    Storage::fake();

    $data = (new ApiDocumentationController())->index()->getData();
    $slugs = array_column($data['filesData'], 'slug');

    // Left to itself Scalar slugifies the title — the first line of a docblock.
    // Rewording that sentence would break every link into the page silently.
    expect($slugs)->toContain('v1')->toContain('v2');
});

it('renders the slug into the sources the page is built from', function () {
    Storage::fake();

    $html = (new ApiDocumentationController())->index()->render();

    // The slug is the version's id on the page: DocLink's anchors start with it.
    expect($html)->toContain('"slug":"v1"')
        ->and($html)->toContain('DskOpenApi.createApiReference');
});

it('still serves a hidden version by direct URL', function () {
    Storage::fake();
    config()->set('laravel-api.hidden_versions', ['v2']);

    $response = (new ApiDocumentationController())->source('v2');

    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toHaveKey('openapi');
});

it('keeps the default script in the config and the controller the same', function () {
    $config = require __DIR__ . '/../../../config/laravel-api.php';

    // The env fallback in the config file is the pinned default.
    expect($config['documentation']['script'] ?? ApiDocumentationController::DEFAULT_SCRIPT)->toBe(ApiDocumentationController::DEFAULT_SCRIPT);
});

it('passes the OAuth redirect URI when set', function () {
    Storage::fake();
    config()->set('laravel-api.documentation.oauth_redirect_url', 'https://docs.example.com/api/doc');

    expect((new ApiDocumentationController())->index()->getData()['documentationOptions']['oauthRedirectUrl'])->toBe('https://docs.example.com/api/doc');
});

it('applies laravel-api.doc_middleware to the documentation routes', function () {
    config()->set('laravel-api.doc_middleware', ['web']);

    expect(\Dskripchenko\LaravelApi\Facades\ApiModule::getDocMiddleware())->toBe(['web']);
});
