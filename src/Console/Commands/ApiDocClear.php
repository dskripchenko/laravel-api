<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Clear cached OpenAPI spec files.
 *
 * ApiDocumentationController caches built specs to `openapi_path` and only
 * rebuilds them when the file is missing (or in debug mode). On deployments
 * where the storage directory persists (volumes), stale specs survive
 * releases — run this command on deploy, or rely on `optimize:clear`
 * (the provider registers it via ServiceProvider::optimizes()).
 */
class ApiDocClear extends Command
{
    protected $signature = 'api:doc-clear
        {--api-version= : Clear only a specific API version spec}';

    protected $description = 'Clear cached OpenAPI spec files (they rebuild on next /api/doc request)';

    public function handle(): int
    {
        $folder = (string) config('laravel-api.openapi_path', 'public/openapi');
        $version = $this->option('api-version');

        if ($version) {
            $path = "{$folder}/{$version}.json";
            if (Storage::exists($path)) {
                Storage::delete($path);
                $this->info("Cleared cached spec: {$path}");
            } else {
                $this->info("Nothing to clear: {$path}");
            }

            return self::SUCCESS;
        }

        $files = Storage::exists($folder) ? Storage::files($folder) : [];
        $specs = array_values(array_filter($files, static fn (string $f): bool => str_ends_with($f, '.json')));
        if ($specs !== []) {
            Storage::delete($specs);
        }
        $this->info('Cleared ' . count($specs) . ' cached OpenAPI spec(s).');

        return self::SUCCESS;
    }
}
