<?php

namespace Dskripchenko\LaravelApi\Traits;

use Dskripchenko\LaravelApi\Services\OpenApi\OpenApiRenderer;
use phpDocumentor\Reflection\DocBlockFactory;

/**
 * The documentation side of an API class: its OpenAPI 3.0 document, and the
 * hooks and flags that shape it — templates, security schemes,
 * `$useResponseTemplates`, `$responseEnvelope`.
 *
 * The document itself is rendered by {@see OpenApiRenderer}.
 */
trait OpenApiTrait
{
    public static $useResponseTemplates = false;

    /**
     * The envelope every JSON body travels in — what ApiResponseHelper::say()
     * really returns, so the spec can stop describing the payload as if it
     * were the whole response.
     *
     * `false` documents the payload alone (the historical behaviour). `true`
     * documents `{success: boolean, payload: <schema>}`, the package's own
     * envelope. An array is a custom envelope in the template shorthand, with
     * the string `'{payload}'` marking where the response schema goes:
     *
     *   ['success!' => 'boolean', 'data!' => '{payload}', 'meta' => '@Meta']
     *
     * With the envelope on, every template — the default `Error` and
     * `Success` included — describes a payload, and every response that has a
     * schema is wrapped: the 2xx ones and the error ones alike, because the
     * runtime wraps them all.
     *
     * @var bool|array<string, mixed>
     */
    public static $responseEnvelope = false;

    /** @deprecated 6.1 Unused: docblocks are read by OperationDocParser. Removed in 7.0. */
    protected static ?DocBlockFactory $docBlockFactory = null;

    /**
     * @deprecated 6.1 Unused: the templates are cached by SchemaRegistry. Removed in 7.0.
     * @var array<class-string, array<string, mixed>>
     */
    protected static array $cachedRawTemplatesByClass = [];

    /**
     * @deprecated 6.1 Unused: the middleware tags are cached by OperationDocParser. Removed in 7.0.
     * @var array<class-string, array<string, array>>
     */
    protected static array $middlewareInputTagCache = [];

    /**
     * The OpenAPI 3.0 document of this API version.
     *
     * @param string $version
     * @return array
     * @throws \ReflectionException
     */
    public static function getOpenApiConfig(string $version)
    {
        return (new OpenApiRenderer(static::class))->render($version);
    }

    /**
     * @return array
     */
    protected static function getOpenApiTemplates()
    {
        return []; //override in final class
    }

    /**
     * @return array
     */
    protected static function getOpenApiSecurityDefinitions(): array
    {
        return []; //override in final class
    }
}
