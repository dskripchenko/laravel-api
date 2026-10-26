<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Transports\Mcp;

use Dskripchenko\LaravelApi\Runtime\ApiInvocation;
use Dskripchenko\LaravelApi\Runtime\Dispatcher;
use Dskripchenko\LaravelApi\Operations\Doc\OperationDocParser;

/**
 * One API version as an MCP server: `initialize`, `ping`, `tools/list`,
 * `tools/call` — JSON-RPC 2.0 messages in, answers out, nothing about HTTP.
 *
 * Stateless: no session is kept between messages, so every message stands on
 * its own and any instance of the application can answer it.
 */
final class McpServer
{
    /** The protocol revisions understood, newest first. */
    public const PROTOCOL_VERSIONS = ['2025-11-25', '2025-06-18', '2025-03-26'];

    public const PARSE_ERROR = -32700;
    public const INVALID_REQUEST = -32600;
    public const METHOD_NOT_FOUND = -32601;
    public const INVALID_PARAMS = -32602;

    /**
     * @param  class-string  $apiClass
     */
    public function __construct(
        private readonly string $apiClass,
        private readonly string $version,
        private readonly McpToolset $tools,
        private readonly Dispatcher $dispatcher,
    ) {
    }

    /**
     * The answer to one message, or null for a notification — which gets none.
     *
     * @param  mixed  $message  The decoded JSON-RPC message.
     * @param  array<string, string>  $headers  Headers to pass on to the actions a tool calls.
     */
    public function handle(mixed $message, array $headers = []): ?array
    {
        if (!is_array($message) || array_is_list($message) || ($message['jsonrpc'] ?? null) !== '2.0') {
            return self::error(null, self::INVALID_REQUEST, 'Not a JSON-RPC 2.0 message; batches are not supported.');
        }

        $isRequest = array_key_exists('id', $message);
        $id = $message['id'] ?? null;

        // A notification, or the client answering a request of ours: neither
        // gets a reply.
        if (!$isRequest || !isset($message['method'])) {
            return null;
        }

        if (!is_string($message['method'])) {
            return self::error($id, self::INVALID_REQUEST, 'The method has to be a string.');
        }

        $params = $message['params'] ?? [];
        if (!is_array($params)) {
            return self::error($id, self::INVALID_PARAMS, 'The params have to be an object.');
        }

        return match ($message['method']) {
            'initialize' => self::result($id, $this->initialize($params)),
            'ping' => self::result($id, new \stdClass()),
            'tools/list' => self::result($id, ['tools' => $this->tools->tools()]),
            'tools/call' => $this->call($id, $params, $headers),
            default => self::error($id, self::METHOD_NOT_FOUND, "Method not found: {$message['method']}"),
        };
    }

    private function initialize(array $params): array
    {
        $requested = $params['protocolVersion'] ?? null;
        $version = in_array($requested, self::PROTOCOL_VERSIONS, true) ? $requested : self::PROTOCOL_VERSIONS[0];

        $doc = OperationDocParser::parse((new \ReflectionClass($this->apiClass))->getDocComment());

        $result = [
            'protocolVersion' => $version,
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => [
                'name' => (string) (config('laravel-api.mcp.name') ?: config('app.name', 'laravel-api')),
                'version' => $this->version,
            ],
        ];

        $instructions = trim($doc->summary . "\n\n" . $doc->description);
        if ($instructions !== '') {
            $result['instructions'] = $instructions;
        }

        return $result;
    }

    private function call(mixed $id, array $params, array $headers): array
    {
        $name = $params['name'] ?? null;
        $operation = is_string($name) ? $this->tools->find($name) : null;
        if ($operation === null) {
            return self::error($id, self::INVALID_PARAMS, 'Unknown tool: ' . (is_string($name) ? $name : '(none)'));
        }

        $arguments = $params['arguments'] ?? [];
        if (!is_array($arguments)) {
            return self::error($id, self::INVALID_PARAMS, 'The arguments have to be an object.');
        }

        $result = $this->dispatcher->dispatch(new ApiInvocation(
            $this->version,
            $operation->controllerKey,
            $operation->actionKey,
            $arguments,
            $headers,
        ));

        // A failed action is a tool result, not a protocol error: the model
        // reads it and may correct the call.
        $answer = [
            'content' => [[
                'type' => 'text',
                'text' => (string) json_encode($result->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]],
            'isError' => !$result->success,
        ];

        if (is_array($result->payload) && !array_is_list($result->payload)) {
            $answer['structuredContent'] = $result->payload;
        }

        return self::result($id, $answer);
    }

    private static function result(mixed $id, mixed $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    public static function error(mixed $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
