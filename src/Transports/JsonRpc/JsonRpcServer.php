<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Transports\JsonRpc;

use Dskripchenko\LaravelApi\Runtime\ApiInvocation;
use Dskripchenko\LaravelApi\Runtime\ApiResult;
use Dskripchenko\LaravelApi\Runtime\Dispatcher;
use Dskripchenko\LaravelApi\Transports\NamedOperations;

/**
 * One API version over JSON-RPC 2.0: every action a method named
 * `controller.action`, called with its input as by-name params.
 *
 * The action's payload is the result. An action that fails is an error: `-32602`
 * (Invalid params) for a validation failure — status 422 — and `-32000` for the
 * rest, with the HTTP status and the payload — `errorKey` and all — in `data`.
 * `rpc.discover` answers with the OpenRPC document of the version.
 */
final class JsonRpcServer
{
    public const PARSE_ERROR = -32700;
    public const INVALID_REQUEST = -32600;
    public const METHOD_NOT_FOUND = -32601;
    public const INVALID_PARAMS = -32602;
    public const ACTION_FAILED = -32000;

    public function __construct(
        private readonly NamedOperations $operations,
        private readonly Dispatcher $dispatcher,
    ) {
    }

    /**
     * The answer to a message or a batch: one response, a list of them, or
     * null when nothing is answered — a notification, a batch of them.
     */
    public function handle(mixed $message): ?array
    {
        if (!is_array($message) || !array_is_list($message)) {
            return $this->single($message);
        }

        if ($message === []) {
            return self::error(null, self::INVALID_REQUEST, 'An empty batch.');
        }

        $responses = array_values(array_filter(
            array_map(fn (mixed $item): ?array => $this->single($item), $message),
            static fn (?array $response): bool => $response !== null
        ));

        return $responses === [] ? null : $responses;
    }

    private function single(mixed $message): ?array
    {
        if (!is_array($message) || array_is_list($message) && $message !== []
            || ($message['jsonrpc'] ?? null) !== '2.0'
            || !isset($message['method']) || !is_string($message['method'])
        ) {
            return self::error(is_array($message) ? self::idOf($message) : null, self::INVALID_REQUEST, 'Not a JSON-RPC 2.0 request.');
        }

        $isNotification = !array_key_exists('id', $message);
        $id = self::idOf($message);

        $response = $this->call($id, $message['method'], $message['params'] ?? []);

        // A notification is carried out and never answered, not even with an error.
        return $isNotification ? null : $response;
    }

    private function call(mixed $id, string $method, mixed $params): array
    {
        if ($method === 'rpc.discover') {
            return self::result($id, (new OpenRpcDocument($this->operations))->toArray());
        }

        $operation = $this->operations->find($method);
        if ($operation === null) {
            return self::error($id, self::METHOD_NOT_FOUND, "Method not found: {$method}");
        }

        if (!is_array($params) || array_is_list($params) && $params !== []) {
            return self::error($id, self::INVALID_PARAMS, 'The params have to be an object: they are passed by name.');
        }

        $result = $this->dispatcher->dispatch(new ApiInvocation(
            $this->operations->version,
            $operation->controllerKey,
            $operation->actionKey,
            $params,
        ));

        return $result->success ? self::result($id, $result->payload) : self::failure($id, $result);
    }

    private static function failure(mixed $id, ApiResult $result): array
    {
        $code = $result->status === 422 ? self::INVALID_PARAMS : self::ACTION_FAILED;
        $message = $result->message() ?? $result->errorKey() ?? 'The action failed.';

        return self::error($id, $code, $message, ['status' => $result->status, 'payload' => $result->payload]);
    }

    /** The id of a message, when it is one JSON-RPC allows: a string, a number or null. */
    private static function idOf(array $message): mixed
    {
        $id = $message['id'] ?? null;

        return is_string($id) || is_int($id) || is_float($id) ? $id : null;
    }

    private static function result(mixed $id, mixed $result): array
    {
        return ['jsonrpc' => '2.0', 'result' => $result, 'id' => $id];
    }

    public static function error(mixed $id, int $code, string $message, mixed $data = null): array
    {
        $error = ['code' => $code, 'message' => $message];
        if ($data !== null) {
            $error['data'] = $data;
        }

        return ['jsonrpc' => '2.0', 'error' => $error, 'id' => $id];
    }
}
