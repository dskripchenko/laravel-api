<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Services\Export;

use Illuminate\Support\Arr;

class HttpClientExporter
{
    /**
     * @param array $openApiConfig
     * @param string $version
     * @return string
     */
    public function export(array $openApiConfig, string $version): string
    {
        $baseUrl = Arr::get($openApiConfig, 'servers.0.url', '{{host}}');
        $lines = [];
        $lines[] = "# {$version} API — Generated from OpenAPI spec";
        $lines[] = '';
        $lines[] = "@host = {$baseUrl}";
        $lines[] = '@token = Bearer {{token}}';
        $lines[] = '';

        $paths = Arr::get($openApiConfig, 'paths', []);

        foreach ($paths as $path => $methods) {
            foreach ($methods as $httpMethod => $operation) {
                $lines[] = $this->buildRequest($path, $httpMethod, $operation);
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
    private function buildRequest(string $path, string $httpMethod, array $operation): string
    {
        $summary = Arr::get($operation, 'summary', '');
        $method = strtoupper($httpMethod);

        $lines = [];
        $lines[] = "### {$summary}";

        $queryParams = [];
        $headerLines = [];
        foreach (Arr::get($operation, 'parameters', []) as $param) {
            $in = Arr::get($param, 'in');
            $name = Arr::get($param, 'name', '');
            $example = Arr::get($param, 'example', Arr::get($param, 'schema.default', ''));

            if ($in === 'query') {
                $queryParams[] = "{$name}=" . urlencode((string) $example);
            } elseif ($in === 'header') {
                $headerLines[] = "{$name}: {$example}";
            }
        }

        $url = "{{host}}{$path}";
        if (!empty($queryParams)) {
            $url .= '?' . implode('&', $queryParams);
        }

        $lines[] = "{$method} {$url}";

        foreach ($headerLines as $header) {
            $lines[] = $header;
        }

        $requestBody = Arr::get($operation, 'requestBody.content', []);
        if (!empty($requestBody)) {
            $bodyLines = $this->buildBody($requestBody);
            if (!empty($bodyLines)) {
                $lines = array_merge($lines, $bodyLines);
            }
        }

        $lines[] = '';
        return implode("\n", $lines);
    }

    /**
     * @param array $content
     * @return array
     */
    private function buildBody(array $content): array
    {
        $lines = [];

        if (isset($content['application/json'])) {
            $lines[] = 'Content-Type: application/json';
            $lines[] = '';
            $schema = Arr::get($content, 'application/json.schema', []);
            $example = $this->buildJsonExample(Arr::get($schema, 'properties', []));
            $lines[] = json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            return $lines;
        }

        if (isset($content['application/x-www-form-urlencoded'])) {
            $lines[] = 'Content-Type: application/x-www-form-urlencoded';
            $lines[] = '';
            $props = Arr::get($content, 'application/x-www-form-urlencoded.schema.properties', []);
            $params = [];
            foreach ($props as $name => $prop) {
                $value = Arr::get($prop, 'example', Arr::get($prop, 'default', ''));
                $params[] = "{$name}=" . urlencode((string) $value);
            }
            $lines[] = implode('&', $params);
            return $lines;
        }

        if (isset($content['multipart/form-data'])) {
            $lines[] = 'Content-Type: multipart/form-data; boundary=boundary';
            $lines[] = '';
            $props = Arr::get($content, 'multipart/form-data.schema.properties', []);
            foreach ($props as $name => $prop) {
                $lines[] = '--boundary';
                if (Arr::get($prop, 'format') === 'binary') {
                    $lines[] = "Content-Disposition: form-data; name=\"{$name}\"; filename=\"{$name}\"";
                    $lines[] = '';
                    $lines[] = "< ./path/to/{$name}";
                } else {
                    $lines[] = "Content-Disposition: form-data; name=\"{$name}\"";
                    $lines[] = '';
                    $lines[] = '';
                }
            }
            $lines[] = '--boundary--';
            return $lines;
        }

        return $lines;
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
