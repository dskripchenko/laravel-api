<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Services;

use Illuminate\Support\Arr;

class OpenApiTypeScriptGenerator
{
    /**
     * @param array $openApiConfig
     * @return string
     */
    public function generate(array $openApiConfig): string
    {
        $lines = [];
        $lines[] = '// Auto-generated from OpenAPI spec. Do not edit manually.';
        $lines[] = '';

        $schemas = Arr::get($openApiConfig, 'components.schemas', []);
        if (!empty($schemas)) {
            foreach ($schemas as $name => $schema) {
                $lines[] = $this->generateInterface($name, $schema);
                $lines[] = '';
            }
        }

        $paths = Arr::get($openApiConfig, 'paths', []);
        foreach ($paths as $path => $methods) {
            foreach ($methods as $httpMethod => $operation) {
                $operationId = Arr::get($operation, 'operationId');
                if (!$operationId) {
                    continue;
                }

                $baseName = $this->operationIdToTypeName($operationId);

                $inputType = $this->buildInputType($operation);
                if ($inputType !== null) {
                    $lines[] = $this->generateInterface("{$baseName}Input", $inputType);
                    $lines[] = '';
                }

                $outputType = $this->buildOutputType($operation);
                if ($outputType !== null) {
                    $lines[] = $this->generateInterface("{$baseName}Output", $outputType);
                    $lines[] = '';
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param string $name
     * @param array $schema
     * @return string
     */
    private function generateInterface(string $name, array $schema): string
    {
        if (isset($schema['$ref'])) {
            $refName = $this->resolveRefName($schema['$ref']);
            return "export type {$name} = {$refName};";
        }

        $type = Arr::get($schema, 'type', 'object');

        if ($type === 'array') {
            $itemsType = $this->resolveType(Arr::get($schema, 'items', []));
            return "export type {$name} = {$itemsType}[];";
        }

        if ($type !== 'object' || !isset($schema['properties'])) {
            $tsType = $this->mapPrimitiveType($type);
            return "export type {$name} = {$tsType};";
        }

        $properties = Arr::get($schema, 'properties', []);
        $required = Arr::get($schema, 'required', []);
        $lines = [];
        $lines[] = "export interface {$name} {";

        foreach ($properties as $propName => $propSchema) {
            $isRequired = in_array($propName, $required, true);
            $tsType = $this->resolveType($propSchema);
            $comment = $this->buildPropertyComment($propSchema);
            $optional = $isRequired ? '' : '?';

            if ($comment) {
                $lines[] = "  /** {$comment} */";
            }
            $lines[] = "  {$propName}{$optional}: {$tsType};";
        }

        $lines[] = '}';
        return implode("\n", $lines);
    }

    /**
     * @param array $schema
     * @return string
     */
    private function resolveType(array $schema): string
    {
        if (isset($schema['$ref'])) {
            return $this->resolveRefName($schema['$ref']);
        }

        $type = Arr::get($schema, 'type', 'string');

        if (isset($schema['enum'])) {
            return implode(' | ', array_map(fn($v) => "'{$v}'", $schema['enum']));
        }

        if ($type === 'array') {
            $items = Arr::get($schema, 'items', []);
            $itemType = $this->resolveType($items);
            return "{$itemType}[]";
        }

        if ($type === 'object') {
            $properties = Arr::get($schema, 'properties', []);
            if (empty($properties)) {
                return 'Record<string, unknown>';
            }
            return $this->buildInlineObject($properties, Arr::get($schema, 'required', []));
        }

        return $this->mapPrimitiveType($type, Arr::get($schema, 'format'));
    }

    /**
     * @param array $properties
     * @param array $required
     * @return string
     */
    private function buildInlineObject(array $properties, array $required = []): string
    {
        $parts = [];
        foreach ($properties as $propName => $propSchema) {
            $isRequired = in_array($propName, $required, true);
            $tsType = $this->resolveType($propSchema);
            $optional = $isRequired ? '' : '?';
            $parts[] = "{$propName}{$optional}: {$tsType}";
        }
        return '{ ' . implode('; ', $parts) . ' }';
    }

    /**
     * @param string $type
     * @param string|null $format
     * @return string
     */
    private function mapPrimitiveType(string $type, ?string $format = null): string
    {
        return match ($type) {
            'integer', 'number' => 'number',
            'boolean' => 'boolean',
            'file' => 'File',
            'string' => match ($format) {
                'binary' => 'File',
                default => 'string',
            },
            default => 'unknown',
        };
    }

    /**
     * @param string $ref
     * @return string
     */
    private function resolveRefName(string $ref): string
    {
        $parts = explode('/', $ref);
        return end($parts);
    }

    /**
     * @param string $operationId
     * @return string
     */
    private function operationIdToTypeName(string $operationId): string
    {
        return implode('', array_map('ucfirst', preg_split('/[_\-]/', $operationId)));
    }

    /**
     * @param array $operation
     * @return array|null
     */
    private function buildInputType(array $operation): ?array
    {
        $properties = [];
        $required = [];

        foreach (Arr::get($operation, 'parameters', []) as $param) {
            $in = Arr::get($param, 'in');
            if ($in === 'header') {
                continue;
            }

            $name = Arr::get($param, 'name', '');
            $schema = Arr::get($param, 'schema', []);
            $properties[$name] = $schema;

            if (!empty($param['description'])) {
                $properties[$name]['description'] = $param['description'];
            }

            if (Arr::get($param, 'required', false)) {
                $required[] = $name;
            }
        }

        $requestBody = Arr::get($operation, 'requestBody.content', []);
        foreach ($requestBody as $contentType => $content) {
            $schema = Arr::get($content, 'schema', []);

            if (isset($schema['$ref'])) {
                return $schema;
            }

            $bodyProps = Arr::get($schema, 'properties', []);
            $bodyRequired = Arr::get($schema, 'required', []);

            foreach ($bodyProps as $name => $propSchema) {
                $properties[$name] = $propSchema;
            }
            $required = array_merge($required, $bodyRequired);
        }

        if (empty($properties)) {
            return null;
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_unique($required),
        ];
    }

    /**
     * @param array $operation
     * @return array|null
     */
    private function buildOutputType(array $operation): ?array
    {
        $responses = Arr::get($operation, 'responses', []);
        $successResponse = Arr::get($responses, '200', []);
        $schema = Arr::get($successResponse, 'content.application/json.schema', []);

        if (empty($schema)) {
            return null;
        }

        return $schema;
    }

    /**
     * @param array $schema
     * @return string
     */
    private function buildPropertyComment(array $schema): string
    {
        $parts = [];

        $format = Arr::get($schema, 'format');
        if ($format) {
            $parts[] = "@format {$format}";
        }

        $description = Arr::get($schema, 'description', '');
        if ($description) {
            $parts[] = $description;
        }

        return implode(' — ', $parts);
    }
}
