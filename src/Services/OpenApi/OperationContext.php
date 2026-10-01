<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Services\OpenApi;

/**
 * The operation a dynamic `@input [method]` / `@output [method]` is asked about.
 *
 * One controller method may serve many routes: a generic CRUD controller is
 * registered under a controller key per entity, and the fields it accepts
 * depend on which entity the route belongs to. Without being told which route
 * the spec is being generated for, a schema method can only answer for all of
 * them at once — which in practice means answering nothing.
 *
 * The generator builds one of these per operation and hands it to the schema
 * method through the container: declare a parameter typed `OperationContext`
 * (any name), or any of the scalar names `$version`, `$controllerKey`,
 * `$actionKey`, `$httpMethod`. Methods that declare none of them are called
 * exactly as before.
 */
final class OperationContext
{
    /**
     * @param  string  $version  The API version the spec is generated for (`v1`).
     * @param  class-string  $apiClass  The BaseApi subclass serving that version.
     * @param  string  $controllerKey  The controller key from getMethods() — the URL segment.
     * @param  string  $actionKey  The action key from getMethods() — the URL segment.
     * @param  class-string  $controllerClass  The controller class serving the action.
     * @param  string  $controllerMethod  The controller method the action points at.
     * @param  string  $httpMethod  The HTTP verb, lowercase (`get`, `post`, ...).
     * @param  string  $tag  `input` or `output` — which side of the operation is asked for.
     * @param  array<string, mixed>  $actionOptions  The action's definition from getMethods(), as written there.
     */
    public function __construct(
        public readonly string $version,
        public readonly string $apiClass,
        public readonly string $controllerKey,
        public readonly string $actionKey,
        public readonly string $controllerClass,
        public readonly string $controllerMethod,
        public readonly string $httpMethod,
        public readonly string $tag = 'input',
        public readonly array $actionOptions = [],
    ) {
    }

    /** The operationId the generator gives this operation. */
    public function operationId(): string
    {
        return "{$this->controllerKey}_{$this->actionKey}";
    }

    public function isInput(): bool
    {
        return $this->tag === 'input';
    }

    public function isOutput(): bool
    {
        return $this->tag === 'output';
    }

    /** A copy of the context asking about the other side of the operation. */
    public function forTag(string $tag): self
    {
        return new self(
            $this->version,
            $this->apiClass,
            $this->controllerKey,
            $this->actionKey,
            $this->controllerClass,
            $this->controllerMethod,
            $this->httpMethod,
            $tag,
            $this->actionOptions,
        );
    }

    /**
     * The parameters handed to `app()->call()`. Keyed by the class name, the
     * context binds to a parameter of that type whatever it is called; keyed by
     * name, the scalars bind to parameters called exactly that.
     *
     * @return array<string, mixed>
     */
    public function callParameters(): array
    {
        return [
            self::class => $this,
            'version' => $this->version,
            'controllerKey' => $this->controllerKey,
            'actionKey' => $this->actionKey,
            'httpMethod' => $this->httpMethod,
        ];
    }
}
