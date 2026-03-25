<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Console\Commands;

use Dskripchenko\LaravelApi\Facades\ApiModule;
use Dskripchenko\LaravelApi\Services\Export\CurlExporter;
use Dskripchenko\LaravelApi\Services\Export\HttpClientExporter;
use Dskripchenko\LaravelApi\Services\Export\MarkdownExporter;
use Dskripchenko\LaravelApi\Services\Export\PostmanCollectionExporter;
use Illuminate\Console\Command;

class ApiExport extends Command
{
    protected $signature = 'api:export
        {--format=postman : Export format (postman, http, markdown, curl)}
        {--output= : Output file path}
        {--api-version= : Generate only for specific API version}';

    protected $description = 'Export API spec in various formats (Postman, HTTP Client, Markdown, cURL)';

    private const FORMAT_MAP = [
        'postman' => ['class' => PostmanCollectionExporter::class, 'ext' => 'json'],
        'http' => ['class' => HttpClientExporter::class, 'ext' => 'http'],
        'markdown' => ['class' => MarkdownExporter::class, 'ext' => 'md'],
        'curl' => ['class' => CurlExporter::class, 'ext' => 'sh'],
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
        $exporter = new $exporterClass();

        $versionList = ApiModule::getApiVersionList();
        $filterVersion = $this->option('api-version');

        $results = [];

        foreach ($versionList as $version => $api) {
            if ($filterVersion && $filterVersion !== $version) {
                continue;
            }

            $this->info("Exporting {$format} for {$version}...");
            $config = $api::getOpenApiConfig($version);
            $results[$version] = $exporter->export($config, $version);
        }

        if (empty($results)) {
            $this->warn('No API versions found.');
            return self::FAILURE;
        }

        $output = $this->option('output');

        if ($output) {
            $dir = dirname($output);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($output, implode("\n\n", $results));
            $this->info("Written to {$output}");
        } else {
            foreach ($results as $version => $content) {
                $filename = "{$version}.{$ext}";
                file_put_contents($filename, $content);
                $this->info("Written to {$filename}");
            }
        }

        return self::SUCCESS;
    }
}
