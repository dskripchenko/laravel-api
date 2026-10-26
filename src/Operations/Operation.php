<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Operations;

/**
 * One enabled action of one API version, as getMethods() declares it.
 *
 * Only what the map says — nothing from the docblocks and nothing about how a
 * transport reaches it. The router, the documentation, the linter and the
 * dispatcher all read actions through this, so the rules for turning an action
 * entry into a method name, the HTTP methods and the flags live in one place
 * ({@see OperationRegistry}).
 *
 * The controller is taken as written: a missing or wrong class is not an error
 * here, because each consumer reacts to it its own way — the linter reports it,
 * the dispatcher answers 404. {@see isCallable()} says whether it can be called.
 */
final class Operation
{
    /**
     * @param  string  $version  The version the registry was built for; empty when the caller does not know it.
     * @param  class-string  $apiClass  The BaseApi subclass declaring the action.
     * @param  string  $controllerKey  The controller key from getMethods() — the URL segment.
     * @param  string  $actionKey  The action key from getMethods() — the URL segment.
     * @param  string  $controllerClass  The `controller` of the entry, as written; empty when there is none.
     * @param  string  $controllerMethod  The controller method the action points at.
     * @param  string[]  $httpMethods  The HTTP methods, as written; `['post']` by default.
     * @param  string|null  $name  The custom route name suffix (`name`), if any.
     * @param  bool  $deprecated  The `deprecated` flag of the action, its controller or the version.
     * @param  array  $security  The action's `security`.
     * @param  array<string, mixed>  $options  The action's definition, as written; empty for a non-array entry.
     */
    public function __construct(
        public readonly string $version,
        public readonly string $apiClass,
        public readonly string $controllerKey,
        public readonly string $actionKey,
        public readonly string $controllerClass,
        public readonly string $controllerMethod,
        public readonly array $httpMethods,
        public readonly ?string $name,
        public readonly bool $deprecated,
        public readonly array $security,
        public readonly array $options,
    ) {
    }

    /** Whether the controller method exists and can be dispatched to. */
    public function isCallable(): bool
    {
        return $this->controllerClass !== ''
            && method_exists($this->controllerClass, $this->controllerMethod);
    }

    /** The callable string `app()->call()` takes: `Controller@method`. */
    public function callable(): string
    {
        return "{$this->controllerClass}@{$this->controllerMethod}";
    }

    /**
     * The id of the operation served by the HTTP method at that position.
     *
     * An action serving several HTTP methods is several operations, and their
     * ids have to be unique: the first method keeps the plain
     * `{controller}_{action}`, the others get the method as a suffix.
     */
    public function operationId(int $methodIndex = 0): string
    {
        $id = "{$this->controllerKey}_{$this->actionKey}";

        if ($methodIndex === 0) {
            return $id;
        }

        return $id . '_' . strtolower((string) (array_values($this->httpMethods)[$methodIndex] ?? ''));
    }

    public function allowsHttpMethod(string $method): bool
    {
        return in_array($method, $this->httpMethods, true);
    }

    /**
     * The middleware cascade (version → controller → action), asked of the
     * API class so that an override of it is honoured.
     *
     * @return array
     */
    public function middleware(): array
    {
        return $this->apiClass::getMiddlewareByControllerAndActionKey($this->controllerKey, $this->actionKey);
    }

    /** @return array */
    public function excludedMiddleware(): array
    {
        return $this->apiClass::getExcludedMiddlewareByControllerAndActionKey($this->controllerKey, $this->actionKey);
    }
}
