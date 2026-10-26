<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Runtime;

use Symfony\Component\HttpFoundation\Response;

/**
 * What an action answered, out of its HTTP response.
 *
 * Read from the body the way an HTTP client reads it — `{success, payload}` —
 * so a transport that is not HTTP hands on exactly what the API documents, and
 * whatever wrote the response (a controller, a middleware refusing, the error
 * handler) is read the same way.
 */
final class ApiResult
{
    /**
     * @param  bool  $success  The `success` of the envelope; for a body without one, whether the status is below 400.
     * @param  mixed  $payload  The `payload` of the envelope; the decoded body without one; the raw body if it is not JSON.
     * @param  int  $status  The HTTP status the action answered with.
     */
    public function __construct(
        public readonly bool $success,
        public readonly mixed $payload,
        public readonly int $status,
        public readonly ?Response $response = null,
    ) {
    }

    public static function fromResponse(Response $response): self
    {
        $status = $response->getStatusCode();
        $content = (string) $response->getContent();

        if ($content === '') {
            return new self($status < 400, null, $status, $response);
        }

        try {
            $body = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new self($status < 400, $content, $status, $response);
        }

        if (is_array($body) && array_key_exists('success', $body) && array_key_exists('payload', $body)) {
            return new self((bool) $body['success'], $body['payload'], $status, $response);
        }

        return new self($status < 400, $body, $status, $response);
    }

    /** The `errorKey` of a failed answer, if it carries one. */
    public function errorKey(): ?string
    {
        if ($this->success || !is_array($this->payload) || !isset($this->payload['errorKey'])) {
            return null;
        }

        return (string) $this->payload['errorKey'];
    }

    /** The `message` of a failed answer, if it carries one. */
    public function message(): ?string
    {
        if ($this->success || !is_array($this->payload) || !isset($this->payload['message'])) {
            return null;
        }

        return (string) $this->payload['message'];
    }
}
