<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Console\Commands;

use Dskripchenko\LaravelApi\Services\ApiCache;
use Illuminate\Console\Command;

/**
 * `api:clear` — removes the map `api:cache` froze; requests build it again.
 *
 * The OpenAPI specs are left alone: `api:doc-clear` removes those.
 */
class ApiClear extends Command
{
    protected $signature = 'api:clear';

    protected $description = 'Remove the cached API map';

    public function handle(): int
    {
        $this->info(ApiCache::clear() ? 'Cached API map cleared.' : 'There was no cached API map.');

        return self::SUCCESS;
    }
}
