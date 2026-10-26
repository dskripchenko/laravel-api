<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Runtime;

/**
 * A call to one action, independent of how it arrived — an MCP tool call, a
 * JSON-RPC request, a queued job.
 */
final class ApiInvocation
{
    /**
     * @param  string  $version  The API version (`v1`).
     * @param  string  $controllerKey  The controller key from getMethods().
     * @param  string  $actionKey  The action key from getMethods().
     * @param  array<string, mixed>  $params  The input, as a request would carry it.
     * @param  array<string, string>  $headers  Headers for the call, on top of those passed on from the request it runs in.
     * @param  string|null  $httpMethod  The HTTP method to call with; the action's first one when null.
     */
    public function __construct(
        public readonly string $version,
        public readonly string $controllerKey,
        public readonly string $actionKey,
        public readonly array $params = [],
        public readonly array $headers = [],
        public readonly ?string $httpMethod = null,
    ) {
    }
}
