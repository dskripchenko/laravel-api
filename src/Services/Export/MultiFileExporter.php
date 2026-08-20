<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Services\Export;

/**
 * A format that is a directory rather than a file.
 *
 * Postman is one JSON document and an `.http` file is one file, so a string was
 * enough for every format this package had. Bruno is a folder: a manifest, an
 * environment and one `.bru` per request, and flattening that into a string
 * would produce something no tool reads.
 *
 * `export()` is still implemented — for a spec narrowed to a single endpoint it
 * is exactly one request, which is the whole point of exporting one.
 */
interface MultiFileExporter extends Exporter
{
    /**
     * @param  array<string, mixed>  $openApiConfig
     * @return array<string, string> relative path => file contents
     */
    public function files(array $openApiConfig, string $version): array;
}
