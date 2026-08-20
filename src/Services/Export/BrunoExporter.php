<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Services\Export;

use Illuminate\Support\Arr;

/**
 * A Bruno collection.
 *
 * Bruno keeps requests as plain files in a directory — one `.bru` per request,
 * a manifest at the root, environments beside it — which is why it is worth
 * exporting to at all: a collection lives in the repository, next to the code
 * that produces it, and a diff of it is readable. A Postman collection is one
 * JSON blob and tells a reviewer nothing.
 *
 * That same shape is why this exporter is the first one here that cannot be a
 * string.
 */
class BrunoExporter implements MultiFileExporter
{
    /**
     * The whole collection, as the files it consists of.
     *
     * @param  array<string, mixed>  $openApiConfig
     * @return array<string, string>
     */
    public function files(array $openApiConfig, string $version): array
    {
        $title = Arr::get($openApiConfig, 'info.title', 'API') ?: 'API';
        $baseUrl = Arr::get($openApiConfig, 'servers.0.url', 'http://localhost/api');

        $files = [
            'bruno.json' => $this->manifest("{$title} {$version}"),
            'environments/default.bru' => $this->environment($baseUrl),
        ];

        $sequence = [];

        foreach ($this->operations($openApiConfig) as $operation) {
            $folder = $this->slug($operation['tag']);
            $sequence[$folder] = ($sequence[$folder] ?? 0) + 1;

            $name = $this->fileName($operation);
            $files["{$folder}/{$name}.bru"] = $this->request($operation, $openApiConfig, $sequence[$folder]);
        }

        return $files;
    }

    /**
     * The requests as one document.
     *
     * Meaningful for a spec narrowed to a single endpoint — which is what
     * `api:export --endpoint` hands over, and the only case where a Bruno
     * export is a file rather than a folder.
     *
     * @param  array<string, mixed>  $openApiConfig
     */
    public function export(array $openApiConfig, string $version): string
    {
        $requests = [];
        $sequence = 0;

        foreach ($this->operations($openApiConfig) as $operation) {
            $requests[] = $this->request($operation, $openApiConfig, ++$sequence);
        }

        return implode("\n", $requests);
    }

    /**
     * Every operation in the spec, flattened.
     *
     * @param  array<string, mixed>  $openApiConfig
     * @return list<array<string, mixed>>
     */
    private function operations(array $openApiConfig): array
    {
        $result = [];

        foreach (Arr::get($openApiConfig, 'paths', []) as $path => $methods) {
            foreach ($methods as $httpMethod => $operation) {
                $result[] = [
                    'path' => $path,
                    'method' => strtolower((string) $httpMethod),
                    'operation' => $operation,
                    'tag' => Arr::get($operation, 'tags.0', 'default'),
                    // Counted here rather than looked up later: a version named
                    // `v1.1` puts a dot in the path, and `Arr::get` would read
                    // it as nesting.
                    'siblings' => count($methods),
                ];
            }
        }

        return $result;
    }

    private function manifest(string $name): string
    {
        return json_encode([
            'version' => '1',
            'name' => $name,
            'type' => 'collection',
            'ignore' => ['node_modules', '.git'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    /**
     * The one environment worth generating: where the API is and what it is
     * called with. Anything else would be guessing at somebody's setup.
     */
    private function environment(string $baseUrl): string
    {
        return <<<BRU
        vars {
          baseUrl: {$baseUrl}
          token:
        }

        BRU;
    }

    /**
     * A file name a person can find in a directory listing.
     *
     * The action, and the method too when the same path answers several — two
     * files called `save.bru` in one folder is a collection nobody trusts.
     *
     * @param  array<string, mixed>  $operation
     */
    private function fileName(array $operation): string
    {
        $segments = array_values(array_filter(explode('/', (string) $operation['path'])));
        $last = $this->slug((string) ($segments[count($segments) - 1] ?? 'request'));

        return $operation['siblings'] > 1 ? "{$last}-{$operation['method']}" : $last;
    }

    /**
     * One `.bru` document.
     *
     * @param  array<string, mixed>  $operation
     * @param  array<string, mixed>  $openApiConfig
     */
    private function request(array $operation, array $openApiConfig, int $sequence): string
    {
        $spec = $operation['operation'];
        $method = $operation['method'];
        $name = Arr::get($spec, 'summary') ?: Arr::get($spec, 'operationId', $operation['path']);

        $query = [];
        $headers = [];

        foreach (Arr::get($spec, 'parameters', []) as $parameter) {
            $in = Arr::get($parameter, 'in');
            $key = (string) Arr::get($parameter, 'name', '');
            $value = Arr::get($parameter, 'example', Arr::get($parameter, 'schema.default', ''));

            if ($in === 'query') {
                $query[$key] = $value;
            } elseif ($in === 'header') {
                $headers[$key] = $value;
            }
        }

        foreach ($this->securityHeaders($spec, $openApiConfig) as $key => $value) {
            $headers[$key] = $headers[$key] ?? $value;
        }

        $body = $this->body($spec);
        $url = '{{baseUrl}}' . $operation['path'];

        if (!empty($query)) {
            $url .= '?' . http_build_query(array_map(static fn ($value) => (string) $value, $query));
        }

        $blocks = [];
        $blocks[] = $this->block('meta', [
            'name' => $this->oneLine((string) $name),
            'type' => 'http',
            'seq' => (string) $sequence,
        ]);

        $blocks[] = $this->block($method, [
            'url' => $url,
            'body' => $body['mode'],
            'auth' => 'none',
        ]);

        if (!empty($query)) {
            $blocks[] = $this->block('params:query', $query);
        }

        if (!empty($headers)) {
            $blocks[] = $this->block('headers', $headers);
        }

        if ($body['content'] !== null) {
            $blocks[] = $this->rawBlock("body:{$body['mode']}", $body['content']);
        }

        $description = trim((string) Arr::get($spec, 'description', ''));
        if ($description !== '') {
            $blocks[] = $this->rawBlock('docs', $description);
        }

        return implode("\n", $blocks);
    }

    /**
     * The header a declared security scheme is carried in.
     *
     * Only `apiKey` in a header is filled in, because it is the only one whose
     * shape is known from the spec alone. The value is a variable, not a secret
     * — an exported collection is a thing people commit.
     *
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $openApiConfig
     * @return array<string, string>
     */
    private function securityHeaders(array $spec, array $openApiConfig): array
    {
        $headers = [];

        foreach (Arr::get($spec, 'security', []) as $requirement) {
            foreach (array_keys((array) $requirement) as $scheme) {
                $definition = Arr::get($openApiConfig, "components.securitySchemes.{$scheme}", []);

                if (Arr::get($definition, 'type') === 'apiKey' && Arr::get($definition, 'in') === 'header') {
                    $headers[(string) Arr::get($definition, 'name', 'Authorization')] = '{{token}}';
                }
            }
        }

        return $headers;
    }

    /**
     * What to send, in Bruno's vocabulary.
     *
     * @param  array<string, mixed>  $spec
     * @return array{mode: string, content: string|null}
     */
    private function body(array $spec): array
    {
        $content = Arr::get($spec, 'requestBody.content', []);

        if (isset($content['application/json'])) {
            $properties = Arr::get($content, 'application/json.schema.properties', []);
            $example = [];

            foreach ($properties as $name => $property) {
                $example[$name] = $this->exampleValue($property);
            }

            return [
                'mode' => 'json',
                'content' => json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ];
        }

        if (isset($content['multipart/form-data'])) {
            $properties = Arr::get($content, 'multipart/form-data.schema.properties', []);
            $lines = [];

            foreach ($properties as $name => $property) {
                $lines[] = Arr::get($property, 'format') === 'binary'
                    ? "  {$name}: @file()"
                    : "  {$name}: " . $this->oneLine((string) $this->exampleValue($property));
            }

            return ['mode' => 'multipart-form', 'content' => implode("\n", $lines)];
        }

        if (isset($content['application/x-www-form-urlencoded'])) {
            $properties = Arr::get($content, 'application/x-www-form-urlencoded.schema.properties', []);
            $lines = [];

            foreach ($properties as $name => $property) {
                $lines[] = "  {$name}: " . $this->oneLine((string) $this->exampleValue($property));
            }

            return ['mode' => 'form-urlencoded', 'content' => implode("\n", $lines)];
        }

        return ['mode' => 'none', 'content' => null];
    }

    /**
     * @param  array<string, mixed>  $property
     * @return mixed
     */
    private function exampleValue(array $property)
    {
        if (isset($property['example'])) {
            return $property['example'];
        }

        if (isset($property['default'])) {
            return $property['default'];
        }

        return match (Arr::get($property, 'type', 'string')) {
            'integer' => 0,
            'number' => 0.0,
            'boolean' => false,
            'array' => [],
            'object' => new \stdClass(),
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $pairs
     */
    private function block(string $name, array $pairs): string
    {
        $lines = ["{$name} {"];

        foreach ($pairs as $key => $value) {
            $lines[] = "  {$key}: " . $this->oneLine((string) $value);
        }

        $lines[] = "}\n";

        return implode("\n", $lines);
    }

    private function rawBlock(string $name, string $content): string
    {
        $indented = implode("\n", array_map(
            static fn (string $line): string => $line === '' ? '' : "  {$line}",
            explode("\n", $content)
        ));

        return "{$name} {\n{$indented}\n}\n";
    }

    /**
     * A newline inside a Bruno value ends the value, so a description that
     * wrapped would silently truncate the request's name.
     */
    private function oneLine(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function slug(string $value): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? $value;

        return trim(strtolower($slug), '-') ?: 'request';
    }
}
