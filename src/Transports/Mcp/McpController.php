<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Transports\Mcp;

use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApi\Facades\ApiModule;
use Dskripchenko\LaravelApi\Runtime\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The MCP endpoint of an API version — the Streamable HTTP transport, without
 * streaming: every message is POSTed and answered with one JSON body.
 *
 * GET (a server-sent event stream) and DELETE (ending a session) are what a
 * server keeping sessions offers; this one keeps none and says so with 405.
 */
class McpController
{
    /** @var array<string, McpToolset> Built once per process and version: describing every action is not free. */
    private static array $toolsets = [];

    public function handle(Request $request, string $version): Response
    {
        $apiClass = self::servedVersions()[$version] ?? null;
        if ($apiClass === null) {
            return new JsonResponse(McpServer::error(null, McpServer::INVALID_REQUEST, "No MCP server for version {$version}."), 404);
        }

        // A web page must not be able to drive the server through the
        // reader's browser (DNS rebinding): the transport asks for the Origin
        // to be checked.
        if (!self::originAllowed($request)) {
            return new JsonResponse(McpServer::error(null, McpServer::INVALID_REQUEST, 'Origin not allowed.'), 403);
        }

        if (!$request->isMethod('POST')) {
            return new JsonResponse(
                McpServer::error(null, McpServer::INVALID_REQUEST, 'Only POST: this server keeps no sessions and streams nothing.'),
                405,
                ['Allow' => 'POST']
            );
        }

        try {
            $message = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return new JsonResponse(McpServer::error(null, McpServer::PARSE_ERROR, 'Parse error: ' . $e->getMessage()), 400);
        }

        $server = new McpServer(
            $apiClass,
            $version,
            self::$toolsets[$version] ??= new McpToolset($apiClass, $version),
            app(Dispatcher::class),
        );

        $answer = $server->handle($message);

        if ($answer === null) {
            return new Response('', 202);
        }

        return new JsonResponse($answer, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Forgets the toolsets built so far. */
    public static function flush(): void
    {
        self::$toolsets = [];
    }

    /**
     * The versions served: `laravel-api.mcp.versions`, or every version the
     * documentation lists when that is empty.
     *
     * @return array<string, class-string>
     */
    public static function servedVersions(): array
    {
        $all = ApiModule::getApiVersionList();
        $chosen = (array) config('laravel-api.mcp.versions', []);

        $versions = $chosen === []
            ? array_diff_key($all, array_flip((array) config('laravel-api.hidden_versions', [])))
            : array_intersect_key($all, array_flip($chosen));

        return array_filter(
            $versions,
            static fn ($apiClass): bool => is_string($apiClass) && is_subclass_of($apiClass, BaseApi::class)
        );
    }

    /**
     * No Origin (a client that is not a browser), the application's own, or
     * one `laravel-api.mcp.allowed_origins` lists (`*` for any).
     */
    private static function originAllowed(Request $request): bool
    {
        $origin = $request->headers->get('Origin');
        if ($origin === null || $origin === '') {
            return true;
        }

        $allowed = (array) config('laravel-api.mcp.allowed_origins', []);

        return in_array('*', $allowed, true)
            || in_array($origin, $allowed, true)
            || $origin === $request->getSchemeAndHttpHost();
    }
}
