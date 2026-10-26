<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Operations\Schema;

use Dskripchenko\LaravelApi\Services\OpenApi\DocPatterns;
use Illuminate\Support\Arr;

/**
 * Object schemas from `type ?$name Description` lines.
 *
 * A line is its named pieces — `type`, `format`, `optional`, `variable`,
 * `description` (what {@see \Dskripchenko\LaravelApi\Operations\Doc\DocLine::parts()}
 * returns) — or, for an output line, a `required` flag already worked out.
 * Dot- and bracket-notation (`$address.city`, `$tags[].id`) builds nested
 * objects and arrays.
 */
final class FieldSchemas
{
    /**
     * One `type ?$name Description` line as a flat field: name, description,
     * required, type, and the format, enum, default and example it has.
     *
     * @param  array  $matches
     * @param  array  $defaultsAndExamples
     * @return array
     */
    public static function flatField(array $matches, array $defaultsAndExamples): array
    {
        $descText = Arr::get($matches, 'description', '');
        $enum = self::extractEnum($descText);
        $type = self::safeType(Arr::get($matches, 'type', 'string'));
        $varName = Arr::get($matches, 'variable', '');

        $param = [
            'name' => $varName,
            'description' => $descText,
            'required' => Arr::get($matches, 'optional', '') !== '?',
            'type' => $type,
        ];

        $format = Arr::get($matches, 'format');
        if ($format) {
            $param['format'] = $format;
        }
        if ($enum !== null) {
            $param['enum'] = $enum;
        }
        if (isset($defaultsAndExamples[$varName]['default'])) {
            $param['default'] = $defaultsAndExamples[$varName]['default'];
        }
        if (isset($defaultsAndExamples[$varName]['example'])) {
            $param['example'] = $defaultsAndExamples[$varName]['example'];
        }

        return $param;
    }

    /**
     * Whether a `[method]` returned a JSON Schema object rather than a list of
     * docblock lines.
     *
     * @param  mixed  $value
     * @return bool
     */
    public static function isObjectSchema($value): bool
    {
        if (!is_array($value) || $value === [] || array_is_list($value)) {
            return false;
        }

        if (isset($value['properties']) && is_array($value['properties'])) {
            return true;
        }

        return ($value['type'] ?? null) === 'object';
    }

    /**
     * The docblock lines of an operation as one object schema, so they can be
     * merged with a schema a `[method]` returned.
     *
     * @param  array  $parsedParams
     * @param  array  $defaultsAndExamples
     * @return array
     */
    public static function objectSchema(array $parsedParams, array $defaultsAndExamples = []): array
    {
        $schema = ['type' => 'object', 'properties' => []];

        if (empty($parsedParams)) {
            return $schema;
        }

        if (self::hasNested($parsedParams)) {
            $schema['properties'] = self::nested($parsedParams);
            $required = self::requiredRoots($parsedParams);
        } else {
            $required = [];
            foreach ($parsedParams as $matches) {
                $param = self::flatField($matches, $defaultsAndExamples);
                $prop = ['type' => $param['type']];
                foreach (['format', 'enum', 'default', 'example'] as $key) {
                    if (array_key_exists($key, $param)) {
                        $prop[$key] = $param[$key];
                    }
                }
                $prop['description'] = $param['description'];
                if ($prop['type'] === 'file') {
                    $prop = ['type' => 'string', 'format' => 'binary', 'description' => $param['description']];
                }
                $schema['properties'][$param['name']] = $prop;
                if ($param['required']) {
                    $required[] = $param['name'];
                }
            }
        }

        if (!empty($required)) {
            $schema['required'] = array_values(array_unique($required));
        }

        return $schema;
    }

    /**
     * Merges object schemas left to right: a property declared later replaces
     * the earlier one of the same name, and the `required` lists are united.
     *
     * @param  array  ...$schemas
     * @return array
     */
    public static function merge(array ...$schemas): array
    {
        $merged = ['type' => 'object', 'properties' => []];
        $required = [];

        foreach ($schemas as $schema) {
            foreach ($schema as $key => $value) {
                if ($key === 'properties') {
                    foreach ((array) $value as $name => $property) {
                        $merged['properties'][$name] = $property;
                    }
                } elseif ($key === 'required') {
                    $required = array_merge($required, (array) $value);
                } elseif ($key !== 'type') {
                    $merged[$key] = $value;
                }
            }
        }

        $required = array_values(array_unique(array_filter(
            $required,
            static fn ($name): bool => is_string($name) && $name !== ''
        )));
        if (!empty($required)) {
            $merged['required'] = $required;
        }

        return $merged;
    }

    /**
     * @param array $parsedParams
     * @return bool
     */
    public static function hasNested(array $parsedParams): bool
    {
        foreach ($parsedParams as $param) {
            $variable = is_array($param) ? ($param['variable'] ?? '') : '';
            if (str_contains($variable, '.') || str_contains($variable, '[]')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether a parsed line is required: input lines carry the `?` mark,
     * output lines arrive with a `required` flag already worked out.
     *
     * @param array $param
     * @return bool
     */
    public static function isRequired(array $param): bool
    {
        if (array_key_exists('required', $param) && is_bool($param['required'])) {
            return $param['required'];
        }

        return ($param['optional'] ?? '') !== '?';
    }

    /**
     * The required top-level names of a nested set of lines: a root counts
     * when its own line (`$address`, or `$tags[]` for a list of scalars) is
     * not marked optional. A root known only through its children says
     * nothing about itself.
     *
     * @param array $parsedParams
     * @return string[]
     */
    public static function requiredRoots(array $parsedParams): array
    {
        $declared = [];
        foreach ($parsedParams as $param) {
            $variable = $param['variable'] ?? '';
            $parts = explode('.', str_replace('[]', '', $variable));
            if (count($parts) !== 1) {
                continue;
            }
            // `$tags` decides over `$tags[]` whatever the order.
            if (str_contains($variable, '[]') && array_key_exists($parts[0], $declared)) {
                continue;
            }
            $declared[$parts[0]] = self::isRequired($param);
        }

        return array_keys(array_filter($declared));
    }

    /**
     * @param array $parsedParams
     * @return array
     */
    public static function nested(array $parsedParams): array
    {
        $groups = [];
        foreach ($parsedParams as $param) {
            $variable = $param['variable'] ?? '';
            $type = self::safeType($param['type'] ?? 'string');
            $description = $param['description'] ?? '';
            $isArray = str_contains($variable, '[]');

            $parts = explode('.', str_replace('[]', '', $variable));
            $root = $parts[0];
            $groups[$root] = $groups[$root] ?? [];

            if (count($parts) === 1 && !$isArray) {
                // The root's own line. It may come after its children, so it
                // must not wipe what they already put there.
                $groups[$root]['declaredType'] = $type;
                $groups[$root]['description'] = $description;
                continue;
            }

            if ($isArray) {
                $groups[$root]['isArray'] = true;
            }

            if (count($parts) === 1) {
                // `$tags[]` — the element of a list of scalars.
                $groups[$root]['itemType'] = $type;
                if (!isset($groups[$root]['description'])) {
                    $groups[$root]['description'] = $description;
                }
                continue;
            }

            $child = $parts[1];
            $property = ['type' => $type, 'description' => $description];
            if (!empty($param['format'])) {
                $property['format'] = $param['format'];
            }
            $groups[$root]['properties'][$child] = $property;
            if (self::isRequired($param)) {
                $groups[$root]['required'][] = $child;
            }
        }

        return self::nestedRecursive($groups);
    }

    /**
     * @param array $groups
     * @return array
     */
    private static function nestedRecursive(array $groups): array
    {
        $result = [];
        foreach ($groups as $key => $spec) {
            $description = $spec['description'] ?? '';
            $properties = $spec['properties'] ?? null;
            $required = array_values(array_unique($spec['required'] ?? []));
            $declaredType = $spec['declaredType'] ?? null;

            $isArray = !empty($spec['isArray']) || $declaredType === 'array';
            $type = $isArray
                ? 'array'
                : ($declaredType ?? ($properties !== null ? 'object' : 'string'));

            if ($type === 'array' && $properties !== null) {
                $items = ['type' => 'object', 'properties' => $properties];
                if (!empty($required)) {
                    $items['required'] = $required;
                }
                $result[$key] = ['type' => 'array', 'description' => $description, 'items' => $items];
            } elseif ($type === 'array' && isset($spec['itemType'])) {
                $result[$key] = [
                    'type' => 'array',
                    'description' => $description,
                    'items' => ['type' => $spec['itemType']],
                ];
            } elseif ($type === 'object' && $properties !== null) {
                $result[$key] = ['type' => 'object', 'description' => $description, 'properties' => $properties];
                if (!empty($required)) {
                    $result[$key]['required'] = $required;
                }
            } else {
                $result[$key] = ['type' => $type, 'description' => $description];
            }
        }
        return $result;
    }

    /**
     * @param string $description
     * @return array|null
     */
    public static function extractEnum(string &$description): ?array
    {
        if (preg_match('/\[([a-zA-Z0-9_,\-\s]+)\]\s*$/', $description, $matches)) {
            $description = trim(preg_replace('/\[([a-zA-Z0-9_,\-\s]+)\]\s*$/', '', $description));
            return array_map('trim', explode(',', $matches[1]));
        }
        return null;
    }

    /**
     * @param $type
     * @return mixed|string
     */
    public static function safeType(string $type)
    {
        if (!in_array($type, DocPatterns::availableDataTypes())) {
            $type = 'string';
        }
        return $type;
    }
}
