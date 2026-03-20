<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Services\Export;

use Illuminate\Support\Arr;

class CurlExporter
{
    /**
     * @param array $openApiConfig
     * @param string $version
     * @return string
     */
    public function export(array $openApiConfig, string $version): string
    {
        $baseUrl = Arr::get($openApiConfig, 'servers.0.url', 'http://localhost/api');
        $title = Arr::get($openApiConfig, 'info.title', 'API');

        $lines = [];
        $lines[] = '#!/usr/bin/env bash';
        $lines[] = "# {$title} ({$version}) — cURL examples";
        $lines[] = '# Auto-generated from OpenAPI spec. Do not edit manually.';
        $lines[] = '';
        $lines[] = "BASE_URL=\"{$baseUrl}\"";
        $lines[] = 'TOKEN="${API_TOKEN:-}"';
        $lines[] = '';

        $paths = Arr::get($openApiConfig, 'paths', []);

        foreach ($paths as $path => $methods) {
            foreach ($methods as $httpMethod => $operation) {
                $lines[] = $this->buildCurl($path, $httpMethod, $operation);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param string $path
     * @param string $httpMethod
     * @param array $operation
     * @return string
     */
    private function buildCurl(string $path, string $httpMethod, array $operation): string
    {
        $summary = Arr::get($operation, 'summary', $path);
        $method = strtoupper($httpMethod);

        $lines = [];
        $lines[] = "# {$summary}";

        $parts = ["curl -s -X {$method}"];

        $queryParams = [];
        foreach (Arr::get($operation, 'parameters', []) as $param) {
            $in = Arr::get($param, 'in');
            $name = Arr::get($param, 'name', '');
            $example = Arr::get($param, 'example', Arr::get($param, 'schema.default', ''));

            if ($in === 'query') {
                $queryParams[] = "{$name}=" . urlencode((string) $example);
            } elseif ($in === 'header') {
                $parts[] = "  -H \"{$name}: {$example}\"";
            }
        }

        $url = "\${BASE_URL}{$path}";
        if (!empty($queryParams)) {
            $url .= '?' . implode('&', $queryParams);
        }

        $hasSecurity = !empty(Arr::get($operation, 'security', []));
        if ($hasSecurity) {
            $parts[] = '  -H "Authorization: ${TOKEN}"';
        }

        $requestBody = Arr::get($operation, 'requestBody.content', []);
        if (!empty($requestBody)) {
            $bodyParts = $this->buildBody($requestBody);
            $parts = array_merge($parts, $bodyParts);
        }

        $parts[] = "  \"{$url}\"";

        $lines[] = implode(" \\\n", $parts);
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param array $content
     * @return array
     */
    private function buildBody(array $content): array
    {
        $parts = [];

        if (isset($content['application/json'])) {
            $parts[] = '  -H "Content-Type: application/json"';
            $schema = Arr::get($content, 'application/json.schema', []);
            $example = $this->buildJsonExample(Arr::get($schema, 'properties', []));
            $json = json_encode($example, JSON_UNESCAPED_UNICODE);
            $parts[] = "  -d '{$json}'";
            return $parts;
        }

        if (isset($content['application/x-www-form-urlencoded'])) {
            $props = Arr::get($content, 'application/x-www-form-urlencoded.schema.properties', []);
            foreach ($props as $name => $prop) {
                $value = Arr::get($prop, 'example', Arr::get($prop, 'default', ''));
                $parts[] = "  -d \"{$name}=" . addcslashes((string) $value, '"') . "\"";
            }
            return $parts;
        }

        if (isset($content['multipart/form-data'])) {
            $props = Arr::get($content, 'multipart/form-data.schema.properties', []);
            foreach ($props as $name => $prop) {
                if (Arr::get($prop, 'format') === 'binary') {
                    $parts[] = "  -F \"{$name}=@./path/to/{$name}\"";
                } else {
                    $parts[] = "  -F \"{$name}=\"";
                }
            }
            return $parts;
        }

        return $parts;
    }

    /**
     * @param array $properties
     * @return array
     */
    private function buildJsonExample(array $properties): array
    {
        $example = [];
        foreach ($properties as $name => $prop) {
            $type = Arr::get($prop, 'type', 'string');
            $example[$name] = match ($type) {
                'integer' => 0,
                'number' => 0.0,
                'boolean' => false,
                'array' => [],
                'object' => new \stdClass(),
                default => '',
            };
        }
        return $example;
    }
}
