<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Services\Export;

use Dskripchenko\LaravelApi\Services\OpenApi\DocLink;
use Illuminate\Support\Arr;

/**
 * A spec cut down to a single endpoint.
 *
 * This is how one method is exported without a single exporter learning what
 * "one method" is: the config it receives simply has one path in it. Every
 * format then produces the right thing by doing what it already did — a
 * collection of one request, one cURL line, one `.bru`.
 */
final class SpecNarrowing
{
    /**
     * @param  array<string, mixed>  $openApiConfig
     * @return array<string, mixed>|null  null when the spec has no such endpoint
     */
    public static function toEndpoint(
        array $openApiConfig,
        string $version,
        string $controller,
        string $action,
        ?string $httpMethod = null
    ): ?array {
        $path = DocLink::path($version, $controller, $action);
        $methods = Arr::get($openApiConfig, 'paths', [])[$path] ?? null;

        if (!is_array($methods) || $methods === []) {
            return null;
        }

        if ($httpMethod !== null) {
            $key = strtolower($httpMethod);
            $methods = isset($methods[$key]) ? [$key => $methods[$key]] : [];

            if ($methods === []) {
                return null;
            }
        }

        return array_merge($openApiConfig, ['paths' => [$path => $methods]]);
    }

    /**
     * `integration.template.contract` → its three parts.
     *
     * Dot notation because that is how the package names its own routes —
     * `api.integration.template.contract` — so the thing one copies out of a
     * log or a route list is the thing one pastes here.
     *
     * @return array{0: string, 1: string, 2: string}|null
     */
    public static function parse(string $endpoint): ?array
    {
        $parts = explode('.', trim($endpoint, '.'));

        if (count($parts) !== 3) {
            return null;
        }

        foreach ($parts as $part) {
            if (trim($part) === '') {
                return null;
            }
        }

        return [$parts[0], $parts[1], $parts[2]];
    }
}
