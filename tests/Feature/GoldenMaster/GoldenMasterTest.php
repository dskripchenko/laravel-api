<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Components\BaseModule;
use Dskripchenko\LaravelApi\Services\Export\BrunoExporter;
use Dskripchenko\LaravelApi\Services\Export\CurlExporter;
use Dskripchenko\LaravelApi\Services\Export\Exporter;
use Dskripchenko\LaravelApi\Services\Export\HttpClientExporter;
use Dskripchenko\LaravelApi\Services\Export\MarkdownExporter;
use Dskripchenko\LaravelApi\Services\Export\PostmanCollectionExporter;
use Dskripchenko\LaravelApi\Services\Linter\LintIssue;
use Dskripchenko\LaravelApi\Services\Linter\OpenApiLinter;
use Dskripchenko\LaravelApi\Services\OpenApiTypeScriptGenerator;

/**
 * Golden master for the operation-model refactor.
 *
 * Everything the package derives from getMethods() and the docblocks — the
 * OpenAPI spec, the routes, the TypeScript types, every export, the lint
 * report — is pinned here byte for byte. The refactor moves that derivation
 * into a registry, a descriptor builder and renderers; these snapshots are
 * what proves the output did not move with it.
 *
 * A snapshot that changes has to be explained in the PR that changes it.
 * Regenerate with `vendor/bin/pest --update-snapshots`.
 */

/** @return array<string, array{class-string, string}> */
function goldenMasterApis(): array
{
    return [
        'fixture v1' => [\Tests\Fixtures\Versions\v1\TestApi::class, 'v1'],
        'fixture v2' => [\Tests\Fixtures\Versions\v2\TestApi::class, 'v2'],
        'template' => [\Tests\Fixtures\OpenApi\TemplateApi::class, 'v1'],
        'extended' => [\Tests\Fixtures\OpenApi\ExtendedApi::class, 'v1'],
        'deprecated alias' => [\Tests\Fixtures\OpenApi\DeprecatedAliasApi::class, 'v1'],
        'context' => [\Tests\Fixtures\OpenApi\ContextApi::class, 'v1'],
        'envelope' => [\Tests\Fixtures\OpenApi\EnvelopeApi::class, 'v1'],
        'custom envelope' => [\Tests\Fixtures\OpenApi\CustomEnvelopeApi::class, 'v1'],
        'multi method' => [\Tests\Fixtures\OpenApi\MultiMethodApi::class, 'v1'],
        'nested' => [\Tests\Fixtures\OpenApi\NestedApi::class, 'v1'],
        'shorthand' => [\Tests\Fixtures\OpenApi\ShorthandApi::class, 'v1'],
        'lint broken' => [\Tests\Fixtures\Linter\BrokenApi::class, 'v1'],
        'lint undeclared input' => [\Tests\Fixtures\Linter\UndeclaredInputApi::class, 'v1'],
        'lint warning only' => [\Tests\Fixtures\Linter\WarningOnlyApi::class, 'v1'],
        'example v1' => [\Dskripchenko\LaravelApiExample\Versions\v1\Api::class, 'v1'],
        'example v1.1' => [\Dskripchenko\LaravelApiExample\Versions\v1_1\Api::class, 'v1.1'],
        'example v1.2' => [\Dskripchenko\LaravelApiExample\Versions\v1_2\Api::class, 'v1.2'],
        'example v2' => [\Dskripchenko\LaravelApiExample\Versions\v2\Api::class, 'v2'],
    ];
}

function goldenMasterJson(mixed $value): string
{
    return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

/**
 * The spec, or what generating it throws — a fixture the generator rejects
 * is behaviour too, and the refactor must not quietly start accepting it.
 */
function goldenMasterSpec(string $api, string $version): array
{
    try {
        return ['spec' => $api::getOpenApiConfig($version)];
    } catch (\Throwable $e) {
        return ['throws' => get_class($e), 'message' => $e->getMessage()];
    }
}

dataset('golden master apis', goldenMasterApis());

it('keeps the OpenAPI spec', function (string $api, string $version) {
    // As /api/doc serves it: the empty maps of the schemas as JSON objects.
    expect(goldenMasterJson(\Dskripchenko\LaravelApi\Operations\Schema\JsonSchemas::encodable(goldenMasterSpec($api, $version))))->toMatchSnapshot();
})->with('golden master apis');

it('keeps the TypeScript types', function (string $api, string $version) {
    $result = goldenMasterSpec($api, $version);
    $output = isset($result['spec'])
        ? (new OpenApiTypeScriptGenerator())->generate($result['spec'])
        : goldenMasterJson($result);

    expect($output)->toMatchSnapshot();
})->with('golden master apis');

it('keeps every export', function (string $api, string $version) {
    $result = goldenMasterSpec($api, $version);
    if (!isset($result['spec'])) {
        expect(goldenMasterJson($result))->toMatchSnapshot();

        return;
    }

    /** @var array<string, Exporter> $exporters */
    $exporters = [
        'postman' => new PostmanCollectionExporter(),
        'http' => new HttpClientExporter(),
        'markdown' => new MarkdownExporter(),
        'curl' => new CurlExporter(),
        'bruno' => new BrunoExporter(),
    ];

    $outputs = [];
    foreach ($exporters as $format => $exporter) {
        $outputs[$format] = $exporter->export($result['spec'], $version);
    }
    $outputs['bruno files'] = (new BrunoExporter())->files($result['spec'], $version);

    expect(goldenMasterJson($outputs))->toMatchSnapshot();
})->with('golden master apis');

it('keeps the lint report', function (string $api, string $version) {
    $issues = (new OpenApiLinter())
        ->withUnroutedMethods()
        ->lintVersionList([$version => $api]);

    $report = array_map(static fn (LintIssue $issue): array => [
        'severity' => $issue->severity,
        'rule' => $issue->rule,
        'where' => $issue->where,
        'message' => $issue->message,
        'hint' => $issue->hint,
    ], $issues);

    expect(goldenMasterJson($report))->toMatchSnapshot();
})->with('golden master apis');

it('keeps the route definitions', function () {
    $versions = [];
    foreach (goldenMasterApis() as $name => [$api, $version]) {
        $versions[str_replace(' ', '-', $name)] = $api;
    }

    $module = new class ($versions) extends BaseModule {
        public function __construct(private array $versions)
        {
        }

        public function getApiVersionList(): array
        {
            return $this->versions;
        }
    };

    try {
        $definitions = $module->getRouteDefinitions();
    } catch (\Throwable $e) {
        $definitions = ['throws' => get_class($e), 'message' => $e->getMessage()];
    }

    expect(goldenMasterJson($definitions))->toMatchSnapshot();
});
