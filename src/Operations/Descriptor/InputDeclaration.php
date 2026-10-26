<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Operations\Descriptor;

use Dskripchenko\LaravelApi\Operations\Schema\FieldSchemas;
use Dskripchenko\LaravelApi\Operations\Schema\SchemaRef;

/**
 * The input of an operation: the schema, and how it was declared.
 *
 * The way of declaring matters to a transport as much as the fields do. Flat
 * `type $name` lines are fields one can send one by one — a query string, a
 * form; dot-notation describes a structured body; a `[method]` returned a
 * schema of its own; `@Model` names the whole body. {@see schema()} is the
 * same input as one JSON Schema object, whatever the declaration.
 */
final class InputDeclaration
{
    /**
     * @param  string[]  $refs  `@Model` lines naming the body, in order (only those that resolve).
     * @param  array[]  $lines  The `type ?$name Description` lines, as their named pieces.
     * @param  array[]  $schemas  The object schemas the `[method]` lines returned.
     * @param  array<string, array{default?: mixed, example?: mixed}>  $defaultsAndExamples  `@default` / `@example`.
     */
    public function __construct(
        public readonly array $refs,
        public readonly array $lines,
        public readonly array $schemas,
        public readonly array $defaultsAndExamples,
    ) {
    }

    /** Whether the lines use dot- or bracket-notation. */
    public function isNested(): bool
    {
        return FieldSchemas::hasNested($this->lines);
    }

    /** Whether a `[method]` returned a schema — then everything is merged into one. */
    public function hasSchemas(): bool
    {
        return $this->schemas !== [];
    }

    /**
     * The lines as flat fields (name, description, required, type, format,
     * enum, default, example). Meaningful when the lines are not nested.
     *
     * @return array[]
     */
    public function fields(): array
    {
        return array_map(
            fn (array $line): array => FieldSchemas::flatField($line, $this->defaultsAndExamples),
            $this->lines
        );
    }

    /** The lines and the `[method]` schemas merged into one object schema. */
    public function mergedSchema(): array
    {
        return FieldSchemas::merge(
            FieldSchemas::objectSchema($this->lines, $this->defaultsAndExamples),
            ...$this->schemas
        );
    }

    /**
     * The properties of the nested lines.
     *
     * @return array<string, array>
     */
    public function nestedProperties(): array
    {
        return FieldSchemas::nested($this->lines);
    }

    /** @return string[] */
    public function requiredRoots(): array
    {
        return FieldSchemas::requiredRoots($this->lines);
    }

    /**
     * The whole input as one JSON Schema object.
     *
     * A body named by `@Model` stands for the input when there are no lines
     * and no `[method]` schema to describe it otherwise.
     */
    public function schema(): array
    {
        if ($this->hasSchemas()) {
            return $this->mergedSchema();
        }

        if ($this->lines === [] && $this->refs !== []) {
            return SchemaRef::to($this->refs[array_key_last($this->refs)]);
        }

        return FieldSchemas::objectSchema($this->lines, $this->defaultsAndExamples);
    }
}
