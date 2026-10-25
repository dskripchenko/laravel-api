<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Console\Commands;

use Dskripchenko\LaravelApi\Controllers\ApiDocumentationController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Copy the documentation page's script into public/.
 *
 * The page loads @dskripchenko/openapi's standalone script from a CDN by
 * default. Closed networks and offline installs have no CDN: this command
 * downloads the configured script once, so the page can serve it itself.
 */
class ApiDocAssets extends Command
{
    protected $signature = 'api:doc-assets
        {--from= : The URL to download (the configured laravel-api.documentation.script by default)}
        {--path=vendor/laravel-api/openapi/standalone.js : Where to put it, relative to public/}';

    protected $description = 'Download the API documentation page script into public/ (for environments without a CDN)';

    public function handle(): int
    {
        $from = (string) ($this->option('from') ?: ApiDocumentationController::documentationScript());
        if (!preg_match('#^https?://#i', $from)) {
            $this->error("The script is already local ({$from}); pass --from with a URL to download.");

            return self::FAILURE;
        }

        $response = Http::timeout(60)->get($from);
        if (!$response->successful() || !str_contains((string) $response->header('Content-Type'), 'javascript')) {
            $this->error("Could not download {$from} (HTTP {$response->status()}).");

            return self::FAILURE;
        }

        $relative = ltrim((string) $this->option('path'), '/');
        $target = public_path($relative);
        File::ensureDirectoryExists(dirname($target));
        File::put($target, $response->body());

        $this->info('Saved ' . number_format(strlen($response->body())) . " bytes to public/{$relative}");
        $this->line("Point the page at it: LARAVEL_API_DOC_SCRIPT=/{$relative}");

        return self::SUCCESS;
    }
}
