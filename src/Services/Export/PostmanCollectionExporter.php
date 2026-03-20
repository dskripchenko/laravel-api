<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Services\Export;

use Illuminate\Support\Arr;

class PostmanCollectionExporter
{
    /**
     * @param array $openApiConfig
     * @param string $version
     * @return string
     */
    public function export(array $openApiConfig, string $version): string
    {
        $baseUrl = Arr::get($openApiConfig, 'servers.0.url', '{{baseUrl}}');
        $title = Arr::get($openApiConfig, 'info.title', 'API') . " {$version}";

        $folders = [];
        $paths = Arr::get($openApiConfig, 'paths', []);

        foreach ($paths as $path => $methods) {
            foreach ($methods as $httpMethod => $operation) {
                $tags = Arr::get($operation, 'tags', ['default']);
                $folderName = $tags[0] ?? 'default';

                $item = $this->buildItem($path, $httpMethod, $operation, $baseUrl);
                $folders[$folderName][] = $item;
            }
        }

        $items = [];
        foreach ($folders as $folderName => $folderItems) {
            $items[] = [
                'name' => $folderName,
                'item' => $folderItems,
            ];
        }

        $collection = [
            'info' => [
                'name' => $title,
                'description' => Arr::get($openApiConfig, 'info.description', ''),
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'variable' => [
                ['key' => 'baseUrl', 'value' => $baseUrl],
                ['key' => 'token', 'value' => ''],
            ],
            'item' => $items,
        ];

        return json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param string $path
     * @param string $httpMethod
     * @param array $operation
     * @param string $baseUrl
     * @return array
     */
    private function buildItem(string $path, string $httpMethod, array $operation, string $baseUrl): array
    {
        $summary = Arr::get($operation, 'summary', $path);
        $operationId = Arr::get($operation, 'operationId', '');
        $description = Arr::get($operation, 'description', '');

        $url = [
            'raw' => "{{baseUrl}}{$path}",
            'host' => ['{{baseUrl}}'],
            'path' => array_values(array_filter(explode('/', $path))),
        ];

        $queryParams = [];
        $headerParams = [];
        foreach (Arr::get($operation, 'parameters', []) as $param) {
            $in = Arr::get($param, 'in');
            $name = Arr::get($param, 'name', '');
            $paramDescription = Arr::get($param, 'description', '');
            $example = Arr::get($param, 'example', Arr::get($param, 'schema.default', ''));

            if ($in === 'query') {
                $queryParams[] = [
                    'key' => $name,
                    'value' => (string) $example,
                    'description' => $paramDescription,
                ];
            } elseif ($in === 'header') {
                $headerParams[] = [
                    'key' => $name,
                    'value' => (string) $example,
                    'description' => $paramDescription,
                ];
            }
        }

        if (!empty($queryParams)) {
            $url['query'] = $queryParams;
        }

        $item = [
            'name' => $summary ?: $operationId,
            'request' => [
                'method' => strtoupper($httpMethod),
                'header' => $headerParams,
                'url' => $url,
                'description' => $description,
            ],
        ];

        $requestBody = Arr::get($operation, 'requestBody.content', []);
        if (!empty($requestBody)) {
            $item['request']['body'] = $this->buildBody($requestBody);
        }

        return $item;
    }

    /**
     * @param array $content
     * @return array
     */
    private function buildBody(array $content): array
    {
        if (isset($content['multipart/form-data'])) {
            $props = Arr::get($content, 'multipart/form-data.schema.properties', []);
            $formData = [];
            foreach ($props as $name => $prop) {
                $type = Arr::get($prop, 'format') === 'binary' ? 'file' : 'text';
                $formData[] = [
                    'key' => $name,
                    'value' => '',
                    'type' => $type,
                    'description' => Arr::get($prop, 'description', ''),
                ];
            }
            return ['mode' => 'formdata', 'formdata' => $formData];
        }

        if (isset($content['application/json'])) {
            $schema = Arr::get($content, 'application/json.schema', []);
            $example = $this->buildJsonExample($schema);
            return [
                'mode' => 'raw',
                'raw' => json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                'options' => ['raw' => ['language' => 'json']],
            ];
        }

        if (isset($content['application/x-www-form-urlencoded'])) {
            $props = Arr::get($content, 'application/x-www-form-urlencoded.schema.properties', []);
            $urlEncoded = [];
            foreach ($props as $name => $prop) {
                $urlEncoded[] = [
                    'key' => $name,
                    'value' => (string) Arr::get($prop, 'example', Arr::get($prop, 'default', '')),
                    'description' => Arr::get($prop, 'description', ''),
                ];
            }
            return ['mode' => 'urlencoded', 'urlencoded' => $urlEncoded];
        }

        return ['mode' => 'raw', 'raw' => ''];
    }

    /**
     * @param array $schema
     * @return array
     */
    private function buildJsonExample(array $schema): array
    {
        $properties = Arr::get($schema, 'properties', []);
        $example = [];
        foreach ($properties as $name => $prop) {
            $example[$name] = $this->getExampleValue($prop);
        }
        return $example;
    }

    /**
     * @param array $prop
     * @return mixed
     */
    private function getExampleValue(array $prop)
    {
        if (isset($prop['example'])) {
            return $prop['example'];
        }

        if (isset($prop['default'])) {
            return $prop['default'];
        }

        $type = Arr::get($prop, 'type', 'string');
        return match ($type) {
            'integer' => 0,
            'number' => 0.0,
            'boolean' => false,
            'array' => [],
            'object' => new \stdClass(),
            default => '',
        };
    }
}
