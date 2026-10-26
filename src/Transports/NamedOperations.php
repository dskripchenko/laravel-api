<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Transports;

use Dskripchenko\LaravelApi\Operations\Operation;

/**
 * The actions of a version under the names a transport calls them by:
 * `controller.action`.
 *
 * Not the operationId: `a_b` + `c` and `a` + `b_c` are both `a_b_c`, while a
 * dot cannot be mistaken. What a name cannot carry is replaced by `_`.
 *
 * From the route map alone — finding the action for a call does not need the
 * others described. An action is left out with `'{transport}' => false` in its
 * definition or in its controller's (`'mcp' => false`, `'jsonrpc' => false`).
 */
final class NamedOperations
{
    /** @var array<string, Operation>|null */
    private ?array $operations = null;

    /**
     * @param  class-string  $apiClass
     * @param  string  $transport  The getMethods() key that leaves an action out.
     */
    public function __construct(
        public readonly string $apiClass,
        public readonly string $version,
        public readonly string $transport,
    ) {
    }

    public static function nameOf(Operation $operation): string
    {
        return preg_replace('/[^A-Za-z0-9_\-]/', '_', $operation->controllerKey)
            . '.'
            . preg_replace('/[^A-Za-z0-9_\-]/', '_', $operation->actionKey);
    }

    /** @return array<string, Operation> Name → operation, in declaration order. */
    public function all(): array
    {
        if ($this->operations !== null) {
            return $this->operations;
        }

        $operations = [];
        foreach ($this->apiClass::operations($this->version)->controllers() as $controller) {
            if (($controller->options[$this->transport] ?? true) === false) {
                continue;
            }

            foreach ($controller->operations as $operation) {
                if (($operation->options[$this->transport] ?? true) !== false && $operation->isCallable()) {
                    $operations[self::nameOf($operation)] = $operation;
                }
            }
        }

        return $this->operations = $operations;
    }

    public function find(string $name): ?Operation
    {
        return $this->all()[$name] ?? null;
    }
}
