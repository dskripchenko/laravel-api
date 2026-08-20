<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Services\Export;

/**
 * One export format.
 *
 * Everything an exporter needs is in the OpenAPI config it is handed, which is
 * also how a single endpoint is exported: the config is narrowed to one path
 * and one method before it arrives, and no exporter has to know that anything
 * unusual is going on.
 */
interface Exporter
{
    /**
     * The whole handed-over spec, as one document.
     *
     * @param  array<string, mixed>  $openApiConfig
     */
    public function export(array $openApiConfig, string $version): string;
}
