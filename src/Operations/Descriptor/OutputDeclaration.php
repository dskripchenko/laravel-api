<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Operations\Descriptor;

/**
 * The success payload of an operation, as `@output` describes it — without
 * the response envelope, which belongs to the transport.
 */
final class OutputDeclaration
{
    /**
     * @param  string  $description  The payload's description.
     * @param  array  $schema  An object schema, or a `$ref` to a template.
     */
    public function __construct(
        public readonly string $description,
        public readonly array $schema,
    ) {
    }
}
