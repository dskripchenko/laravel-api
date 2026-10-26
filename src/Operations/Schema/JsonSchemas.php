<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Operations\Schema;

/**
 * Schemas made ready for json_encode().
 *
 * PHP has one array for lists and maps, and json_encode() writes an empty one
 * as `[]` — a list. Where JSON Schema expects a map — `properties`, `$defs` —
 * that is not an empty map but a type error, and a validator says so.
 */
final class JsonSchemas
{
    /** The keywords whose value is a map from names to schemas. */
    private const MAPS = ['properties', 'patternProperties', '$defs', 'definitions'];

    /** The schema with every empty map written as `{}`, at any depth. */
    public static function encodable(mixed $schema): mixed
    {
        if (!is_array($schema)) {
            return $schema;
        }

        foreach ($schema as $key => $value) {
            if (in_array($key, self::MAPS, true) && $value === []) {
                $schema[$key] = new \stdClass();
            } else {
                $schema[$key] = self::encodable($value);
            }
        }

        return $schema;
    }
}
