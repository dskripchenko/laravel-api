<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Transports\JsonRpc;

use Dskripchenko\LaravelApi\Runtime\Dispatcher;
use Dskripchenko\LaravelApi\Transports\NamedOperations;
use Dskripchenko\LaravelApi\Transports\ServedVersions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The JSON-RPC 2.0 endpoint of an API version: a request or a batch POSTed as
 * JSON, the answer as JSON — 200 whatever the outcome, the outcome being in
 * the body, and 204 when everything posted was a notification.
 */
class JsonRpcController
{
    public function handle(Request $request, string $version): Response
    {
        $apiClass = ServedVersions::of('jsonrpc')[$version] ?? null;
        if ($apiClass === null) {
            return new JsonResponse(JsonRpcServer::error(null, JsonRpcServer::INVALID_REQUEST, "No JSON-RPC endpoint for version {$version}."), 404);
        }

        try {
            $message = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return new JsonResponse(JsonRpcServer::error(null, JsonRpcServer::PARSE_ERROR, 'Parse error: ' . $e->getMessage()));
        }

        $server = new JsonRpcServer(
            new NamedOperations($apiClass, $version, 'jsonrpc'),
            app(Dispatcher::class),
        );

        $answer = $server->handle($message);

        if ($answer === null) {
            return new Response('', 204);
        }

        return new JsonResponse($answer, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
