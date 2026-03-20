<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Services\Export;

use Illuminate\Support\Arr;

class MarkdownExporter
{
    /**
     * @param array $openApiConfig
     * @param string $version
     * @return string
     */
    public function export(array $openApiConfig, string $version): string
    {
        $title = Arr::get($openApiConfig, 'info.title', 'API');
        $description = Arr::get($openApiConfig, 'info.description', '');
        $baseUrl = Arr::get($openApiConfig, 'servers.0.url', '');

        $lines = [];
        $lines[] = "# {$title} ({$version})";
        $lines[] = '';
        if ($description) {
            $lines[] = $description;
            $lines[] = '';
        }
        if ($baseUrl) {
            $lines[] = "**Base URL:** `{$baseUrl}`";
            $lines[] = '';
        }

        $lines[] = '## Table of Contents';
        $lines[] = '';

        $paths = Arr::get($openApiConfig, 'paths', []);
        $grouped = $this->groupByTag($paths);

        foreach ($grouped as $tag => $endpoints) {
            $anchor = strtolower(str_replace(' ', '-', $tag));
            $lines[] = "- [{$tag}](#{$anchor})";
        }
        $lines[] = '';

        foreach ($grouped as $tag => $endpoints) {
            $lines[] = "## {$tag}";
            $lines[] = '';

            foreach ($endpoints as $endpoint) {
                $lines[] = $this->buildEndpoint($endpoint['path'], $endpoint['method'], $endpoint['operation']);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param array $paths
     * @return array
     */
    private function groupByTag(array $paths): array
    {
        $grouped = [];
        foreach ($paths as $path => $methods) {
            foreach ($methods as $httpMethod => $operation) {
                $tags = Arr::get($operation, 'tags', ['default']);
                $tag = $tags[0] ?? 'default';
                $grouped[$tag][] = [
                    'path' => $path,
                    'method' => $httpMethod,
                    'operation' => $operation,
                ];
            }
        }
        return $grouped;
    }

    /**
     * @param string $path
     * @param string $httpMethod
     * @param array $operation
     * @return string
     */
    private function buildEndpoint(string $path, string $httpMethod, array $operation): string
    {
        $summary = Arr::get($operation, 'summary', '');
        $description = Arr::get($operation, 'description', '');
        $method = strtoupper($httpMethod);
        $deprecated = Arr::get($operation, 'deprecated', false);

        $lines = [];
        $title = $summary ?: $path;
        if ($deprecated) {
            $title = "~~{$title}~~ (deprecated)";
        }
        $lines[] = "### {$title}";
        $lines[] = '';
        $lines[] = "`{$method} {$path}`";
        $lines[] = '';

        if ($description) {
            $cleanDescription = trim(preg_replace('/^[\w\\\\]+\n/', '', $description));
            if ($cleanDescription) {
                $lines[] = $cleanDescription;
                $lines[] = '';
            }
        }

        $parameters = Arr::get($operation, 'parameters', []);
        $queryParams = array_filter($parameters, fn($p) => Arr::get($p, 'in') === 'query');
        $headerParams = array_filter($parameters, fn($p) => Arr::get($p, 'in') === 'header');

        if (!empty($queryParams)) {
            $lines[] = '**Parameters:**';
            $lines[] = '';
            $lines[] = '| Name | Type | Required | Description |';
            $lines[] = '|------|------|----------|-------------|';
            foreach ($queryParams as $param) {
                $name = Arr::get($param, 'name', '');
                $type = Arr::get($param, 'schema.type', 'string');
                $required = Arr::get($param, 'required', false) ? 'Yes' : 'No';
                $desc = Arr::get($param, 'description', '');
                $lines[] = "| `{$name}` | {$type} | {$required} | {$desc} |";
            }
            $lines[] = '';
        }

        if (!empty($headerParams)) {
            $lines[] = '**Headers:**';
            $lines[] = '';
            $lines[] = '| Name | Required | Description |';
            $lines[] = '|------|----------|-------------|';
            foreach ($headerParams as $param) {
                $name = Arr::get($param, 'name', '');
                $required = Arr::get($param, 'required', false) ? 'Yes' : 'No';
                $desc = Arr::get($param, 'description', '');
                $lines[] = "| `{$name}` | {$required} | {$desc} |";
            }
            $lines[] = '';
        }

        $requestBody = Arr::get($operation, 'requestBody.content', []);
        if (!empty($requestBody)) {
            $lines[] = $this->buildRequestBody($requestBody);
        }

        $responses = Arr::get($operation, 'responses', []);
        if (!empty($responses)) {
            $lines[] = $this->buildResponses($responses);
        }

        $lines[] = '---';
        $lines[] = '';
        return implode("\n", $lines);
    }

    /**
     * @param array $content
     * @return string
     */
    private function buildRequestBody(array $content): string
    {
        $lines = [];
        $lines[] = '**Request Body:**';
        $lines[] = '';

        $contentType = array_key_first($content);
        $lines[] = "Content-Type: `{$contentType}`";
        $lines[] = '';

        $schema = Arr::get($content, "{$contentType}.schema", []);
        $props = Arr::get($schema, 'properties', []);
        $required = Arr::get($schema, 'required', []);

        if (!empty($props)) {
            $lines[] = '| Field | Type | Required | Description |';
            $lines[] = '|-------|------|----------|-------------|';
            foreach ($props as $name => $prop) {
                $type = Arr::get($prop, 'type', 'string');
                if (Arr::get($prop, 'format') === 'binary') {
                    $type = 'file';
                }
                $isRequired = in_array($name, $required, true) ? 'Yes' : 'No';
                $desc = Arr::get($prop, 'description', '');
                $lines[] = "| `{$name}` | {$type} | {$isRequired} | {$desc} |";
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * @param array $responses
     * @return string
     */
    private function buildResponses(array $responses): string
    {
        $lines = [];
        $lines[] = '**Responses:**';
        $lines[] = '';

        foreach ($responses as $code => $response) {
            $desc = Arr::get($response, 'description', '');
            $lines[] = "- `{$code}` — {$desc}";
        }
        $lines[] = '';

        return implode("\n", $lines);
    }
}
