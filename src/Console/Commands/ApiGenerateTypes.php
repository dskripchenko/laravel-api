<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Console\Commands;

use Dskripchenko\LaravelApi\Facades\ApiModule;
use Dskripchenko\LaravelApi\Services\OpenApiTypeScriptGenerator;
use Illuminate\Console\Command;

class ApiGenerateTypes extends Command
{
    protected $signature = 'api:generate-types
        {--output= : Output file path (default: resources/js/shared/api/types.ts)}
        {--api-version= : Generate only for specific API version}';

    protected $description = 'Generate TypeScript interfaces from OpenAPI spec';

    public function handle(): int
    {
        $generator = new OpenApiTypeScriptGenerator();
        $versionList = ApiModule::getApiVersionList();
        $filterVersion = $this->option('api-version');

        $allContent = [];

        foreach ($versionList as $version => $api) {
            if ($filterVersion && $filterVersion !== $version) {
                continue;
            }

            $this->info("Generating types for {$version}...");
            $config = $api::getOpenApiConfig($version);
            $content = $generator->generate($config);
            $allContent[] = $content;
        }

        if (empty($allContent)) {
            $this->warn('No API versions found.');
            return self::FAILURE;
        }

        $output = $this->option('output')
            ?? base_path('resources/js/shared/api/types.ts');

        $dir = dirname($output);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($output, implode("\n", $allContent));
        $this->info("Types written to {$output}");

        return self::SUCCESS;
    }
}
