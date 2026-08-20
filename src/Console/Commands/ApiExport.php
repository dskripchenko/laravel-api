<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Console\Commands;

use Dskripchenko\LaravelApi\Facades\ApiModule;
use Dskripchenko\LaravelApi\Services\Export\BrunoExporter;
use Dskripchenko\LaravelApi\Services\Export\CurlExporter;
use Dskripchenko\LaravelApi\Services\Export\Exporter;
use Dskripchenko\LaravelApi\Services\Export\HttpClientExporter;
use Dskripchenko\LaravelApi\Services\Export\MarkdownExporter;
use Dskripchenko\LaravelApi\Services\Export\MultiFileExporter;
use Dskripchenko\LaravelApi\Services\Export\PostmanCollectionExporter;
use Dskripchenko\LaravelApi\Services\Export\SpecNarrowing;
use Illuminate\Console\Command;

class ApiExport extends Command
{
    protected $signature = 'api:export
        {--format=postman : Export format (postman, http, markdown, curl, bruno)}
        {--output= : Output file path, or directory for formats that are directories}
        {--api-version= : Generate only for specific API version}
        {--endpoint= : One endpoint, in dot notation — version.controller.action}
        {--method= : Which HTTP method of that endpoint, when it answers several}
        {--stdout : Write to standard output instead of a file}';

    protected $description = 'Export API spec in various formats (Postman, HTTP Client, Markdown, cURL, Bruno)';

    private const FORMAT_MAP = [
        'postman' => ['class' => PostmanCollectionExporter::class, 'ext' => 'json'],
        'http' => ['class' => HttpClientExporter::class, 'ext' => 'http'],
        'markdown' => ['class' => MarkdownExporter::class, 'ext' => 'md'],
        'curl' => ['class' => CurlExporter::class, 'ext' => 'sh'],
        'bruno' => ['class' => BrunoExporter::class, 'ext' => 'bru'],
    ];

    public function handle(): int
    {
        $format = $this->option('format');

        if (!isset(self::FORMAT_MAP[$format])) {
            $this->error("Unknown format: {$format}. Available: " . implode(', ', array_keys(self::FORMAT_MAP)));

            return self::FAILURE;
        }

        $exporterClass = self::FORMAT_MAP[$format]['class'];
        $ext = self::FORMAT_MAP[$format]['ext'];
        /** @var Exporter $exporter */
        $exporter = new $exporterClass();

        $endpoint = $this->option('endpoint');

        if ($endpoint !== null && $endpoint !== '') {
            return $this->exportEndpoint($exporter, $ext, (string) $endpoint);
        }

        return $this->exportVersions($exporter, $ext);
    }

    /**
     * One endpoint, in whichever format.
     *
     * The spec is narrowed before the exporter sees it, so nothing about this
     * is a special case for the format: a Postman collection of one request and
     * a single `.bru` are what each of them makes of a spec with one path in
     * it.
     */
    private function exportEndpoint(Exporter $exporter, string $ext, string $endpoint): int
    {
        $parts = SpecNarrowing::parse($endpoint);

        if ($parts === null) {
            $this->error("Not an endpoint: {$endpoint}. Expected version.controller.action, as the route is named.");

            return self::FAILURE;
        }

        [$version, $controller, $action] = $parts;
        $versionList = ApiModule::getApiVersionList();

        if (!isset($versionList[$version])) {
            $this->error("No such API version: {$version}. Known: " . implode(', ', array_keys($versionList)));

            return self::FAILURE;
        }

        $api = $versionList[$version];
        $narrowed = SpecNarrowing::toEndpoint(
            $api::getOpenApiConfig($version),
            $version,
            $controller,
            $action,
            $this->option('method')
        );

        if ($narrowed === null) {
            $this->error("The spec of {$version} has no {$controller}.{$action}" . $this->methodNote() . '.');

            return self::FAILURE;
        }

        $content = $exporter->export($narrowed, $version);
        $output = $this->option('output');

        if ($this->option('stdout') || !$output) {
            $this->line($content);

            return self::SUCCESS;
        }

        $this->write((string) $output, $content);

        return self::SUCCESS;
    }

    private function methodNote(): string
    {
        $method = $this->option('method');

        return $method ? " answering {$method}" : '';
    }

    /**
     * Every version, or the one that was asked for.
     */
    private function exportVersions(Exporter $exporter, string $ext): int
    {
        $versionList = ApiModule::getApiVersionList();
        $filterVersion = $this->option('api-version');
        $output = $this->option('output');
        $multiFile = $exporter instanceof MultiFileExporter;

        if ($multiFile && $this->option('stdout')) {
            $this->error('This format is a directory of files, not a document — export one endpoint, or give --output a directory.');

            return self::FAILURE;
        }

        $results = [];
        $written = 0;

        foreach ($versionList as $version => $api) {
            if ($filterVersion && $filterVersion !== $version) {
                continue;
            }

            $this->info("Exporting {$this->option('format')} for {$version}...");
            $config = $api::getOpenApiConfig($version);

            if ($multiFile) {
                /** @var MultiFileExporter $exporter */
                $directory = rtrim((string) ($output ?: getcwd()), '/') . "/{$version}";

                foreach ($exporter->files($config, $version) as $relative => $contents) {
                    $this->write("{$directory}/{$relative}", $contents);
                    $written++;
                }

                $this->info("Written to {$directory}/ ({$written} files)");
                $written = 0;

                continue;
            }

            $results[$version] = $exporter->export($config, $version);
        }

        if ($multiFile) {
            return self::SUCCESS;
        }

        if (empty($results)) {
            $this->warn('No API versions found.');

            return self::FAILURE;
        }

        if ($this->option('stdout')) {
            $this->line(implode("\n\n", $results));

            return self::SUCCESS;
        }

        if ($output) {
            $this->write((string) $output, implode("\n\n", $results));
            $this->info("Written to {$output}");

            return self::SUCCESS;
        }

        foreach ($results as $version => $content) {
            $filename = "{$version}.{$ext}";
            $this->write($filename, $content);
            $this->info("Written to {$filename}");
        }

        return self::SUCCESS;
    }

    private function write(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $contents);
    }
}
