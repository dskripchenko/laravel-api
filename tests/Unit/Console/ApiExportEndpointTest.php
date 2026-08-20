<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/**
 * Exporting one endpoint rather than a whole version.
 *
 * The reason it exists: an API has hundreds of endpoints and a person wanting
 * to try one does not want a collection of the other ninety-nine. The reason it
 * is not a special case anywhere in the exporters: the spec is narrowed first,
 * so every format keeps doing exactly what it did.
 */
function runExport(array $options): array
{
    $code = Artisan::call('api:export', $options);

    return [$code, Artisan::output()];
}

it('writes one endpoint to standard output', function () {
    [$code, $output] = runExport(['--endpoint' => 'v1.item.list', '--format' => 'curl', '--stdout' => true]);

    expect($code)->toBe(0)
        ->and($output)->toContain('/v1/item/list')
        ->and($output)->not->toContain('/v1/item/create');
});

it('exports one endpoint as a single Bruno request', function () {
    [$code, $output] = runExport(['--endpoint' => 'v1.item.list', '--format' => 'bruno', '--stdout' => true]);

    expect($code)->toBe(0)
        ->and($output)->toContain('meta {')
        ->and($output)->toContain('url: {{baseUrl}}/v1/item/list');
});

it('exports one endpoint as a collection of one request', function () {
    [$code, $output] = runExport(['--endpoint' => 'v1.item.list', '--format' => 'postman', '--stdout' => true]);

    $collection = json_decode(trim($output), true);

    expect($code)->toBe(0)
        ->and($collection['item'])->toHaveCount(1)
        ->and($collection['item'][0]['item'])->toHaveCount(1);
});

it('refuses an endpoint that is not spelled as a route is named', function () {
    [$code, $output] = runExport(['--endpoint' => 'item.list', '--format' => 'curl', '--stdout' => true]);

    expect($code)->toBe(1)
        ->and($output)->toContain('version.controller.action');
});

it('names the versions it knows when given one it does not', function () {
    [$code, $output] = runExport(['--endpoint' => 'v9.item.list', '--format' => 'curl', '--stdout' => true]);

    expect($code)->toBe(1)
        ->and($output)->toContain('v1');
});

it('refuses an endpoint the spec does not describe', function () {
    [$code, $output] = runExport(['--endpoint' => 'v1.item.nonexistent', '--format' => 'curl', '--stdout' => true]);

    expect($code)->toBe(1)
        ->and($output)->toContain('item.nonexistent');
});

it('narrows to one HTTP method when asked', function () {
    [$code, $output] = runExport([
        '--endpoint' => 'v1.item.list',
        '--method' => 'post',
        '--format' => 'curl',
        '--stdout' => true,
    ]);

    // The action answers GET only, so asking for its POST is a question with no
    // answer — and saying so beats exporting the GET as if it had been meant.
    expect($code)->toBe(1)
        ->and($output)->toContain('post');
});

it('refuses to write a directory format to standard output', function () {
    [$code, $output] = runExport(['--format' => 'bruno', '--stdout' => true]);

    expect($code)->toBe(1)
        ->and($output)->toContain('directory');
});

it('writes a Bruno collection as a directory', function () {
    $target = sys_get_temp_dir() . '/laravel-api-bruno-' . getmypid();

    [$code] = runExport(['--format' => 'bruno', '--api-version' => 'v1', '--output' => $target]);

    expect($code)->toBe(0)
        ->and(file_exists("{$target}/v1/bruno.json"))->toBeTrue()
        ->and(file_exists("{$target}/v1/item/list.bru"))->toBeTrue();

    exec('rm -rf ' . escapeshellarg($target));
});
