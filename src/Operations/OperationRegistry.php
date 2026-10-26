<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Operations;

use Illuminate\Support\Arr;

/**
 * The operations of one API class, read once from its prepared methods.
 *
 * Before this, four places walked getMethods() each in its own way — the
 * dispatcher, the route registration, the OpenAPI generator and the linter —
 * and they drifted: an entry one of them understood the others did not. They
 * all read this now.
 *
 * An action entry means:
 * - `'list'` (numeric key) — the action `list`, method `list()`;
 * - `'show' => 'getById'` — the action `show`, method `getById()`;
 * - `'create' => [...]` — the action `create`, method `action` or `create()`;
 * - `'a' => true` — the action `a`, method `a()`;
 * - `'a' => false` — disabled, no operation at all.
 */
final class OperationRegistry
{
    /** @var array<string, array{methods: array, registry: self}> */
    private static array $memo = [];

    /** @var array<string, ControllerDefinition>|null */
    private ?array $controllers = null;

    /**
     * @param  class-string  $apiClass
     */
    public function __construct(
        public readonly string $apiClass,
        public readonly string $version = '',
        private readonly ?array $methods = null,
    ) {
    }

    /**
     * The registry of an API class, built once per prepared methods.
     *
     * The memo is checked against the prepared methods themselves rather than
     * trusted forever: whoever resets BaseApi's cache (a test suite, a package
     * building its own) gets a registry of the new methods. While the cache is
     * untouched the check is a pointer comparison.
     *
     * @param  class-string  $apiClass
     */
    public static function for(string $apiClass, string $version = ''): self
    {
        $methods = $apiClass::getPreparedMethods();
        $key = "{$apiClass}\0{$version}";

        if (!isset(self::$memo[$key]) || self::$memo[$key]['methods'] !== $methods) {
            self::$memo[$key] = [
                'methods' => $methods,
                'registry' => new self($apiClass, $version, $methods),
            ];
        }

        return self::$memo[$key]['registry'];
    }

    public static function flush(): void
    {
        self::$memo = [];
    }

    /**
     * Every controller entry, keyed by controller key, in declaration order.
     *
     * @return array<string, ControllerDefinition>
     */
    public function controllers(): array
    {
        return $this->controllers ??= $this->build();
    }

    /**
     * Every enabled action, in declaration order.
     *
     * @return Operation[]
     */
    public function operations(): array
    {
        $operations = [];
        foreach ($this->controllers() as $controller) {
            foreach ($controller->operations as $operation) {
                $operations[] = $operation;
            }
        }

        return $operations;
    }

    public function find(string|int|null $controllerKey, string|int|null $actionKey): ?Operation
    {
        return ($this->controllers()[(string) $controllerKey] ?? null)?->operations[(string) $actionKey] ?? null;
    }

    /** @return array<string, ControllerDefinition> */
    private function build(): array
    {
        $methods = $this->methods ?? $this->apiClass::getPreparedMethods();
        $versionDeprecated = (bool) Arr::get($methods, 'deprecated', false);

        $controllers = [];
        foreach ((array) Arr::get($methods, 'controllers', []) as $controllerKey => $options) {
            $controllerKey = (string) $controllerKey;
            $options = (array) $options;
            $class = $options['controller'] ?? null;
            $deprecated = $versionDeprecated || (bool) Arr::get($options, 'deprecated', false);

            $operations = [];
            foreach ((array) Arr::get($options, 'actions', []) as $key => $value) {
                $operation = $this->operation($controllerKey, $class, $key, $value, $deprecated);
                if ($operation) {
                    $operations[$operation->actionKey] = $operation;
                }
            }

            $controllers[$controllerKey] = new ControllerDefinition($controllerKey, $class, $options, $operations);
        }

        return $controllers;
    }

    private function operation(string $controllerKey, mixed $class, int|string $key, mixed $value, bool $deprecated): ?Operation
    {
        if ($value === false) {
            return null;
        }

        $actionKey = is_numeric($key) ? $value : $key;
        if (!is_string($actionKey)) {
            return null;
        }

        $options = is_array($value) ? $value : [];

        $method = $actionKey;
        if (is_string($value)) {
            $method = $value;
        } elseif (isset($options['action']) && is_string($options['action'])) {
            $method = $options['action'];
        }

        $httpMethods = $options['method'] ?? ['post'];
        if (!$httpMethods) {
            $httpMethods = ['post'];
        }
        if (!is_array($httpMethods)) {
            $httpMethods = [$httpMethods];
        }

        $name = $options['name'] ?? null;

        return new Operation(
            version: $this->version,
            apiClass: $this->apiClass,
            controllerKey: $controllerKey,
            actionKey: $actionKey,
            controllerClass: is_string($class) ? $class : '',
            controllerMethod: $method,
            httpMethods: $httpMethods,
            name: is_string($name) && $name !== '' ? $name : null,
            deprecated: $deprecated || (bool) ($options['deprecated'] ?? false),
            security: (array) ($options['security'] ?? []),
            options: $options,
        );
    }
}
