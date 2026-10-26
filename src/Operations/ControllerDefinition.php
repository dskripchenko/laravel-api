<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Operations;

/**
 * A controller entry of getMethods() with its enabled actions.
 *
 * Kept next to the operations because some checks are about the entry itself —
 * a missing `controller` key, a class that does not exist, controller-level
 * middleware — and have to be made even when no action of it is enabled.
 */
final class ControllerDefinition
{
    /**
     * @param  string  $key  The controller key — the URL segment.
     * @param  mixed  $class  The `controller` value, as written (null when missing).
     * @param  array<string, mixed>  $options  The whole entry, as written.
     * @param  array<string, Operation>  $operations  Enabled actions, keyed by action key, in declaration order.
     */
    public function __construct(
        public readonly string $key,
        public readonly mixed $class,
        public readonly array $options,
        public readonly array $operations,
    ) {
    }
}
