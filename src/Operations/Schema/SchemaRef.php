<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Operations\Schema;

/**
 * How a schema refers to a template.
 *
 * The model keeps the OpenAPI location, `#/components/schemas/{Name}`, as the
 * one spelling of a reference: hand-written schemas — templates and what a
 * `[method]` returns — already use it and pass through untouched, so any
 * other spelling would have to be translated back and forth. A renderer that
 * is not OpenAPI takes the name with {@see name()}.
 */
final class SchemaRef
{
    public const PREFIX = '#/components/schemas/';

    /** The `$ref` schema pointing at a template. */
    public static function to(string $name): array
    {
        return ['$ref' => self::PREFIX . $name];
    }

    /** The template a `$ref` points at, whatever comes before the last slash. */
    public static function name(string $ref): string
    {
        $pos = strrpos($ref, '/');

        return $pos === false ? $ref : substr($ref, $pos + 1);
    }
}
