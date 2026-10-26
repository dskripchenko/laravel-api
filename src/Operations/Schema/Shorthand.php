<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Operations\Schema;

/**
 * The template shorthand, unfolded into JSON Schema.
 *
 * `'integer!'`, `'string(email)'`, `'@User[]'`, nested maps, one-element lists
 * and hand-written schemas — the notation of getSchemaTemplates() and of a
 * custom response envelope. Nothing here knows about OpenAPI: a `@Model`
 * becomes a {@see SchemaRef}.
 */
final class Shorthand
{
    /**
     * The keys a hand-written schema may carry. A nested array whose keys are
     * all of these (and which names a type, a $ref, properties, items or a
     * composition) is taken as a schema and passed through; any other array
     * is a map of fields — a nested object in the shorthand.
     */
    public const SCHEMA_KEYWORDS = [
        'type', 'format', 'required', 'description', 'properties', 'items', '$ref',
        'enum', 'nullable', 'example', 'examples', 'default', 'minimum', 'maximum',
        'exclusiveMinimum', 'exclusiveMaximum', 'minLength', 'maxLength', 'pattern',
        'minItems', 'maxItems', 'uniqueItems', 'minProperties', 'maxProperties',
        'additionalProperties', 'allOf', 'oneOf', 'anyOf', 'not', 'discriminator',
        'readOnly', 'writeOnly', 'deprecated', 'title', 'multipleOf', 'xml', 'externalDocs',
    ];

    public const SCHEMA_MARKERS = ['type', '$ref', 'properties', 'items', 'allOf', 'oneOf', 'anyOf', 'enum'];

    public const SCHEMA_TYPES = ['object', 'array', 'string', 'integer', 'number', 'boolean', 'null'];

    /**
     * Unfolds a map of fields into an object schema: the shorthand strings,
     * the nested maps, the one-element lists and the hand-written schemas
     * alike, at any depth.
     *
     * A key ending in `!` marks the field required whatever its definition —
     * the way to require a nested object or an array, which have no string
     * to carry the mark.
     *
     * @param array $fields
     * @param bool $keepEmptyRequired  emit `required: []` even when nothing is required
     * @return array
     */
    public static function fields(array $fields, bool $keepEmptyRequired = false): array
    {
        $properties = [];
        $required = [];

        foreach ($fields as $name => $definition) {
            $name = (string) $name;
            $forced = false;

            if (str_ends_with($name, '!')) {
                $name = substr($name, 0, -1);
                $forced = true;
            }

            [$schema, $isRequired] = self::definition($definition);
            $properties[$name] = $schema;

            if ($forced || $isRequired) {
                $required[] = $name;
            }
        }

        $result = ['type' => 'object', 'properties' => $properties];

        if ($required !== [] || $keepEmptyRequired) {
            $result['required'] = array_values(array_unique($required));
        }

        return $result;
    }

    /**
     * One field's definition as a schema, plus whether the field is required.
     *
     *   'string!'                      → a shorthand string
     *   ['url' => 'string']            → a nested object
     *   ['string']                     → an array of strings
     *   [['id' => 'integer!']]         → an array of objects
     *   ['type' => 'string', ...]      → a hand-written schema, passed through
     *
     * @param mixed $definition
     * @return array{0: array, 1: bool}
     */
    public static function definition($definition): array
    {
        if (is_string($definition)) {
            $schema = self::parse($definition);
            $required = (bool) ($schema['required'] ?? false);
            unset($schema['required']);

            return [$schema, $required];
        }

        if (!is_array($definition)) {
            return [['type' => 'string'], false];
        }

        if ($definition === []) {
            return [['type' => 'object'], false];
        }

        // A one-element list is an array of that element.
        if (array_is_list($definition) && count($definition) === 1) {
            [$items] = self::definition($definition[0]);

            return [['type' => 'array', 'items' => $items], false];
        }

        if (self::isExplicitSchema($definition)) {
            $required = false;

            if (isset($definition['required']) && is_bool($definition['required'])) {
                $required = $definition['required'];
                unset($definition['required']);
            }

            if (isset($definition['properties']) && is_array($definition['properties'])) {
                $nested = self::fields($definition['properties']);
                $definition['properties'] = $nested['properties'];

                $requiredList = array_merge(
                    is_array($definition['required'] ?? null) ? $definition['required'] : [],
                    $nested['required'] ?? []
                );
                if ($requiredList !== []) {
                    $definition['required'] = array_values(array_unique($requiredList));
                }
            }

            if (isset($definition['items']) && (is_string($definition['items']) || is_array($definition['items']))) {
                [$definition['items']] = self::definition($definition['items']);
            }

            if (isset($definition['$ref']) && is_string($definition['$ref']) && str_starts_with($definition['$ref'], '@')) {
                $definition['$ref'] = SchemaRef::PREFIX . substr($definition['$ref'], 1);
            }

            return [$definition, $required];
        }

        return [self::fields($definition), false];
    }

    /**
     * Whether an array is a hand-written schema rather than a map of fields.
     *
     * A map whose every key happens to be a schema keyword and whose `type`
     * names a real type — `['type' => 'string', 'format' => 'string']`, two
     * optional fields called type and format — reads as a schema; write such
     * a map with `['type' => ['type' => 'string'], ...]` to disambiguate.
     *
     * @param array $definition
     * @return bool
     */
    public static function isExplicitSchema(array $definition): bool
    {
        if (array_is_list($definition)) {
            return false;
        }

        $hasMarker = false;

        foreach ($definition as $key => $value) {
            $key = (string) $key;

            if (!in_array($key, self::SCHEMA_KEYWORDS, true) && !str_starts_with($key, 'x-')) {
                return false;
            }

            if (in_array($key, self::SCHEMA_MARKERS, true)) {
                $hasMarker = true;
            }
        }

        if (!$hasMarker) {
            return false;
        }

        if (array_key_exists('type', $definition)) {
            $type = $definition['type'];

            if (!is_string($type) || !in_array($type, self::SCHEMA_TYPES, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parses shorthand type string into a full attribute array.
     *
     * Format: "type(format)! Description" — the format, the required mark and
     * the description are all optional.
     * Examples: "integer!", "string(date-time)", "string(email)! Contact email",
     * "string Document identifier".
     * Ref syntax "@ModelName", "@ModelName!" and "@ModelName[]" resolve to $ref.
     *
     * @param string $shorthand
     * @return array
     */
    public static function parse(string $shorthand): array
    {
        $shorthand = trim($shorthand);

        // Everything past the first space is a human description: response
        // schemas were type-only, so consumers of the spec (and anything
        // reading it, from SDK generators to search) saw field names without
        // a hint of what they mean.
        $description = null;
        if (preg_match('/^(\S+)\s+(.+)$/u', $shorthand, $parts) === 1) {
            $shorthand = $parts[1];
            $description = trim($parts[2]);
        }

        $required = false;
        if (str_ends_with($shorthand, '!')) {
            $required = true;
            $shorthand = substr($shorthand, 0, -1);
        }

        // @ref array syntax: @ModelName[] with an optional description.
        // A description next to `$ref` is ignored by OpenAPI 3.0, but an
        // array wrapping a ref is a plain object — so here it survives.
        if (preg_match('/^@(\S+?)\[\]$/u', $shorthand, $m)) {
            $result = [
                'type' => 'array',
                'items' => ['$ref' => SchemaRef::PREFIX . $m[1]],
            ];

            if ($description !== null && $description !== '') {
                $result['description'] = $description;
            }

            if ($required) {
                $result['required'] = true;
            }

            return $result;
        }

        // @ref syntax: @ModelName
        if (str_starts_with($shorthand, '@')) {
            $result = ['$ref' => SchemaRef::PREFIX . substr($shorthand, 1)];

            if ($required) {
                $result['required'] = true;
            }

            return $result;
        }

        $format = null;
        if (preg_match('/^(\w+)\(([^)]+)\)$/', $shorthand, $m)) {
            $shorthand = $m[1];
            $format = $m[2];
        }

        $result = ['type' => $shorthand];

        if ($format !== null) {
            $result['format'] = $format;
        }

        if ($required) {
            $result['required'] = true;
        }

        if ($description !== null && $description !== '') {
            $result['description'] = $description;
        }

        return $result;
    }
}
