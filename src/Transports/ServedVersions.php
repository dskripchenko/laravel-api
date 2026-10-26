<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Transports;

use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApi\Facades\ApiModule;

/**
 * The API versions a transport serves: `laravel-api.{transport}.versions`, or
 * every version the documentation lists when that is empty.
 */
final class ServedVersions
{
    /**
     * @param  string  $transport  The config key of the transport: `mcp`, `jsonrpc`.
     * @return array<string, class-string> Version → Api class.
     */
    public static function of(string $transport): array
    {
        $all = ApiModule::getApiVersionList();
        $chosen = (array) config("laravel-api.{$transport}.versions", []);

        $versions = $chosen === []
            ? array_diff_key($all, array_flip((array) config('laravel-api.hidden_versions', [])))
            : array_intersect_key($all, array_flip($chosen));

        return array_filter(
            $versions,
            static fn ($apiClass): bool => is_string($apiClass) && is_subclass_of($apiClass, BaseApi::class)
        );
    }
}
