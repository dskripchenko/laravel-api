<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Transports\Mcp;

use Dskripchenko\LaravelApi\Operations\DescriptorBuilder;
use Dskripchenko\LaravelApi\Operations\Operation;
use Dskripchenko\LaravelApi\Operations\Schema\JsonSchemas;
use Dskripchenko\LaravelApi\Operations\Schema\SchemaRef;
use Dskripchenko\LaravelApi\Operations\Schema\SchemaRegistry;
use Dskripchenko\LaravelApi\Services\ApiCache;
use Dskripchenko\LaravelApi\Transports\NamedOperations;

/**
 * The actions of one API version as MCP tools.
 *
 * One tool per action, named `controller.action`, described by the first of its
 * HTTP methods — the one a call uses. Its input schema is the action's input as
 * one JSON Schema object; the templates it refers to travel along under
 * `$defs`, since `#/components/schemas/…` means something only inside an
 * OpenAPI document.
 *
 * An action is left out with `'mcp' => false` in its definition, or in its
 * controller's ({@see NamedOperations}).
 */
final class McpToolset
{
    private readonly NamedOperations $operations;

    /** @var array[]|null */
    private ?array $tools = null;

    /**
     * @param  class-string  $apiClass
     */
    public function __construct(
        public readonly string $apiClass,
        public readonly string $version,
    ) {
        $this->operations = new NamedOperations($apiClass, $version, 'mcp');
    }

    /**
     * The tools, as `tools/list` answers with them.
     *
     * @return array[]
     */
    public function tools(): array
    {
        // `php artisan api:cache` described them already.
        return $this->tools ??= ApiCache::mcpTools($this->apiClass, $this->version) ?? $this->describe();
    }

    /**
     * The operation a tool calls, or null when there is no such tool.
     *
     * From the route map alone: a call needs one action, not a description of
     * all of them.
     */
    public function find(string $name): ?Operation
    {
        return $this->operations->find($name);
    }

    /** `controller.action` — see {@see NamedOperations::nameOf()}. */
    public static function toolName(Operation $operation): string
    {
        return NamedOperations::nameOf($operation);
    }

    /** @return array[] */
    private function describe(): array
    {
        $builder = DescriptorBuilder::for($this->apiClass);
        $templates = SchemaRegistry::for($this->apiClass)->all();

        $tools = [];
        foreach ($this->operations->all() as $name => $operation) {
            [$descriptor] = $builder->build($operation);

            $description = trim($descriptor->summary() . "\n\n" . $descriptor->description());
            if ($descriptor->deprecated) {
                $description = trim('Deprecated. ' . $description);
            }

            $tool = [
                'name' => $name,
                // The first line: a docblock's second line runs into its summary.
                'title' => trim(strtok($descriptor->summary(), "\n") ?: '') ?: $name,
                'description' => $description,
                'inputSchema' => JsonSchemas::encodable(self::withDefinitions(self::asObject($descriptor->input->schema()), $templates)),
            ];

            if (strtolower($descriptor->httpMethod) === 'get') {
                $tool['annotations'] = ['readOnlyHint' => true];
            }

            $tools[] = $tool;
        }

        return $tools;
    }

    /**
     * An input schema has to be an object. A whole input named by a template
     * is one, wrapped; an empty input is an object without properties.
     */
    private static function asObject(array $schema): array
    {
        if (isset($schema['$ref'])) {
            return ['type' => 'object', 'allOf' => [$schema]];
        }

        if (($schema['properties'] ?? null) === []) {
            $schema['properties'] = new \stdClass();
        }

        return $schema;
    }

    /**
     * The templates the schema refers to, moved under `$defs` and the
     * references pointed there — following the templates' own references too.
     *
     * @param  array<string, array>  $templates
     */
    private static function withDefinitions(array $schema, array $templates): array
    {
        $definitions = [];
        $pending = self::references($schema);

        while ($pending !== []) {
            $name = array_shift($pending);
            if (isset($definitions[$name]) || !isset($templates[$name])) {
                continue;
            }

            $definitions[$name] = self::pointToDefinitions($templates[$name]);
            array_push($pending, ...self::references($templates[$name]));
        }

        $schema = self::pointToDefinitions($schema);
        if ($definitions !== []) {
            ksort($definitions);
            $schema['$defs'] = $definitions;
        }

        return $schema;
    }

    /** @return string[] */
    private static function references(array $schema): array
    {
        $names = [];
        array_walk_recursive($schema, static function ($value, $key) use (&$names): void {
            if ($key === '$ref' && is_string($value) && str_starts_with($value, SchemaRef::PREFIX)) {
                $names[] = SchemaRef::name($value);
            }
        });

        return array_values(array_unique($names));
    }

    private static function pointToDefinitions(mixed $schema): mixed
    {
        if (!is_array($schema)) {
            return $schema;
        }

        foreach ($schema as $key => $value) {
            if ($key === '$ref' && is_string($value) && str_starts_with($value, SchemaRef::PREFIX)) {
                $schema[$key] = '#/$defs/' . SchemaRef::name($value);
            } else {
                $schema[$key] = self::pointToDefinitions($value);
            }
        }

        return $schema;
    }
}
