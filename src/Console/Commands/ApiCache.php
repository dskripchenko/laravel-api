<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Console\Commands;

use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApi\Operations\Schema\JsonSchemas;
use Dskripchenko\LaravelApi\Operations\OperationRegistry;
use Dskripchenko\LaravelApi\Services\ApiCache as Cache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * `api:cache` — freezes the API map for production, and builds the docs.
 *
 * The map — the prepared methods of every version and the route definitions —
 * goes to `bootstrap/cache/laravel-api.php`, and requests stop rebuilding it.
 * The OpenAPI spec of every version is built too and stored where /api/doc
 * reads it: a version whose spec cannot be built fails the command, and the
 * deployment hears about it before a reader of the docs does. Nothing is
 * written then.
 *
 * Like `route:cache`, it freezes what getMethods() returns now: run it on
 * every deployment, and `api:clear` (or `optimize:clear`) to go back.
 */
class ApiCache extends Command
{
    protected $signature = 'api:cache
        {--no-docs : Do not build the OpenAPI specs}';

    protected $description = 'Cache the API map for faster requests, and build the OpenAPI specs';

    public function handle(): int
    {
        // Whatever this process has read so far may come from the cache being
        // replaced.
        Cache::clear();
        BaseApi::flushPreparedMethods();
        OperationRegistry::flush();

        $module = app('api_module');

        $specs = [];
        if (! $this->option('no-docs')) {
            foreach ($module->getApiVersionList() as $version => $apiClass) {
                if (! is_string($apiClass) || ! is_subclass_of($apiClass, BaseApi::class)) {
                    continue;
                }

                try {
                    $specs[(string) $version] = $apiClass::getOpenApiConfig((string) $version);
                } catch (\Throwable $e) {
                    $this->error("The spec of {$version} cannot be built: {$e->getMessage()}");
                    $this->line('Nothing was cached. `php artisan api:lint` points at what is wrong.');

                    return self::FAILURE;
                }
            }
        }

        try {
            $data = Cache::build($module);
        } catch (\UnexpectedValueException $e) {
            $this->error($e->getMessage());
            $this->line('Nothing was cached: getMethods() has to return plain values to be cached.');

            return self::FAILURE;
        }

        $folder = (string) config('laravel-api.openapi_path', 'public/openapi');
        foreach ($specs as $version => $spec) {
            Storage::put("{$folder}/{$version}.json", (string) json_encode(JsonSchemas::encodable($spec)));
        }

        Cache::write($data);

        $this->info(sprintf(
            'API map cached: %d version(s), %d route(s)%s.',
            count($data['prepared']),
            count($data['routes']),
            $specs === [] ? '' : sprintf('; %d OpenAPI spec(s) built', count($specs))
        ));

        if ($data['mcp'] !== []) {
            $this->info(sprintf(
                'MCP tools cached for %s.',
                implode(', ', array_map(static fn ($v, $e) => "{$v} (" . count($e['tools']) . ')', array_keys($data['mcp']), $data['mcp']))
            ));
        }

        return self::SUCCESS;
    }
}
