<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Operations;

use Dskripchenko\LaravelApi\Operations\Descriptor\DescriptorNote;
use Dskripchenko\LaravelApi\Operations\Descriptor\InputDeclaration;
use Dskripchenko\LaravelApi\Operations\Descriptor\OperationDescriptor;
use Dskripchenko\LaravelApi\Operations\Descriptor\OutputDeclaration;
use Dskripchenko\LaravelApi\Operations\Descriptor\ResponseDeclaration;
use Dskripchenko\LaravelApi\Operations\Doc\DocLine;
use Dskripchenko\LaravelApi\Operations\Doc\OperationDoc;
use Dskripchenko\LaravelApi\Operations\Doc\OperationDocParser;
use Dskripchenko\LaravelApi\Operations\Schema\FieldSchemas;
use Dskripchenko\LaravelApi\Operations\Schema\SchemaRef;
use Dskripchenko\LaravelApi\Operations\Schema\SchemaRegistry;
use Dskripchenko\LaravelApi\Services\OpenApi\DocPatterns;
use Dskripchenko\LaravelApi\Services\OpenApi\OperationContext;
use Illuminate\Support\Arr;

/**
 * Turns an action and its docblock into descriptors — one per HTTP method.
 *
 * Here the lines get their meaning: a `@Model` resolves against the
 * templates, a `[method]` is called and answers with lines or a schema,
 * dot-notation becomes nested objects. What comes out knows nothing of query
 * strings, request bodies or `#/paths`: that is for a renderer to decide.
 */
final class DescriptorBuilder
{
    /** @var DescriptorNote[] What the descriptor being built had to leave out. */
    private array $notes = [];

    /** @var array<int, string> The lines a `[method]` returned (by object id) → that method. */
    private array $returnedBy = [];

    public function __construct(private readonly SchemaRegistry $schemas)
    {
    }

    /** @param class-string $apiClass */
    public static function for(string $apiClass): self
    {
        return new self(SchemaRegistry::for($apiClass));
    }

    /**
     * @return OperationDescriptor[] One per HTTP method of the action, in order.
     * @throws \ReflectionException When the controller or its method does not exist.
     */
    public function build(Operation $operation): array
    {
        $doc = OperationDocParser::parse(
            (new \ReflectionClass($operation->controllerClass))
                ->getMethod($operation->controllerMethod)
                ->getDocComment(),
            $operation->middleware()
        );

        $security = !empty($operation->security)
            ? $operation->security
            : array_map(static fn (string $name): array => [$name => []], $doc->securitySchemes());
        $deprecated = $doc->isDeprecated() || $operation->deprecated;
        $defaultsAndExamples = $doc->defaultsAndExamples();

        $descriptors = [];
        foreach (array_values($operation->httpMethods) as $index => $httpMethod) {
            $operationId = $operation->operationId($index);
            $context = new OperationContext(
                $operation->version,
                $operation->apiClass,
                $operation->controllerKey,
                $operation->actionKey,
                $operation->controllerClass,
                $operation->controllerMethod,
                strtolower((string) $httpMethod),
                'input',
                $operation->options,
                $operationId,
            );

            $this->notes = [];
            $this->returnedBy = [];

            $input = $this->input($doc, $operation->controllerClass, $defaultsAndExamples, $context);
            $headers = $this->headers($doc);
            $responses = $this->responses($doc);
            $output = $responses === []
                ? $this->output($doc, $operation->controllerClass, $context->forTag('output'))
                : null;

            $descriptors[] = new OperationDescriptor(
                $operation,
                $doc,
                $context,
                (string) $httpMethod,
                $operationId,
                $deprecated,
                $security,
                $input,
                $headers,
                $output,
                $responses,
                $this->notes,
            );
        }

        return $descriptors;
    }

    private function input(OperationDoc $doc, string $class, array $defaultsAndExamples, OperationContext $context): InputDeclaration
    {
        $refs = [];
        $lines = [];
        $schemas = [];

        foreach ($doc->inputs() as $line) {
            $candidates = [$line];

            if ($line->kind() === DocLine::CALLABLE) {
                $result = $this->callSchemaMethod($class, $line->parts()['callable'], $context);

                if (FieldSchemas::isObjectSchema($result)) {
                    $schemas[] = $result;
                    continue;
                }

                $candidates = $this->linesOf($result, 'input', $line->parts()['callable']);
            }

            foreach ($candidates as $candidate) {
                $kind = $candidate->kind();

                if ($kind === DocLine::MODEL_REF) {
                    $model = $candidate->parts()['model'];
                    if ($this->schemas->enabled() && $this->schemas->has($model)) {
                        $refs[] = $model;
                    } else {
                        $this->noteUnresolved($candidate, $model);
                    }
                    continue;
                }

                if ($kind === DocLine::FIELD) {
                    $lines[] = $candidate->parts();
                } else {
                    $this->noteMalformed($candidate);
                }
            }
        }

        return new InputDeclaration($refs, $lines, $schemas, $defaultsAndExamples);
    }

    /** @return array[] */
    private function headers(OperationDoc $doc): array
    {
        $headers = [];
        foreach ($doc->headers() as $line) {
            if ($line->kind() !== DocLine::FIELD) {
                continue;
            }

            $parts = $line->parts();
            $headers[] = [
                'name' => Arr::get($parts, 'variable', ''),
                'description' => Arr::get($parts, 'description', ''),
                'required' => Arr::get($parts, 'optional', '') !== '?',
                'type' => FieldSchemas::safeType(Arr::get($parts, 'type', 'string')),
            ];
        }

        return $headers;
    }

    /** @return ResponseDeclaration[] */
    private function responses(OperationDoc $doc): array
    {
        $responses = [];
        foreach ($doc->responses() as $line) {
            if (!preg_match(DocPatterns::response(), $line->body, $matches)) {
                continue;
            }

            $template = Arr::get($matches, 'template', '');
            $responses[] = new ResponseDeclaration(
                $matches['code'],
                $template ? trim($template, '{}') : null,
                Arr::get($matches, 'description', ''),
            );
        }

        return $responses;
    }

    /**
     * `@output [method]` unfolds into the lines it returns, or into an object
     * schema merged over whatever the other lines describe.
     */
    private function output(OperationDoc $doc, string $class, OperationContext $context): OutputDeclaration
    {
        $lines = [];
        $explicitSchemas = [];

        foreach ($doc->outputs() as $line) {
            if ($line->kind() === DocLine::CALLABLE) {
                $result = $this->callSchemaMethod($class, $line->parts()['callable'], $context);

                if (FieldSchemas::isObjectSchema($result)) {
                    $explicitSchemas[] = $result;
                } else {
                    array_push($lines, ...$this->linesOf($result, 'output', $line->parts()['callable']));
                }

                continue;
            }

            $lines[] = $line;
        }

        $base = $this->outputByLines($lines);

        // A `{Template}` line wins over everything, as it does among plain lines.
        if (!empty($explicitSchemas) && !isset($base['schema'])) {
            $base = [
                'description' => 'Response payload',
                'schema' => FieldSchemas::merge(
                    array_intersect_key($base, array_flip(['properties', 'required'])),
                    ...$explicitSchemas
                ),
            ];
        }

        if (isset($base['schema'])) {
            $schema = $base['schema'];
        } else {
            $schema = [];
            foreach (['type', 'properties', 'required'] as $key) {
                if (isset($base[$key])) {
                    $schema[$key] = $base[$key];
                }
            }
        }

        return new OutputDeclaration($base['description'] ?? 'Response payload', $schema);
    }

    /**
     * @param  DocLine[]  $lines  The @output lines.
     * @return array The payload: `description` with `schema`, or with `type`, `properties`, `required`.
     */
    private function outputByLines(array $lines): array
    {
        $properties = [];
        $templates = $this->schemas->enabled();

        foreach ($lines as $line) {
            $kind = $line->kind();
            $parts = $line->parts();

            if ($templates && $kind === DocLine::TEMPLATE
                && isset($parts['template']) && $this->schemas->has($parts['template'])
            ) {
                return [
                    'description' => 'Response payload',
                    'schema' => SchemaRef::to($parts['template']),
                ];
            }

            if ($kind === DocLine::MODEL_REF) {
                $modelName = $parts['model'];
                $variable = Arr::get($parts, 'variable', '');

                if ($templates && $variable && $this->schemas->has($modelName)) {
                    $properties[$variable] = !empty($parts['isArray'])
                        ? [
                            'type' => 'array',
                            'items' => SchemaRef::to($modelName),
                            'description' => Arr::get($parts, 'description', ''),
                        ]
                        : SchemaRef::to($modelName) + ['description' => Arr::get($parts, 'description', '')];
                    continue;
                }

                // Resolving, and naming no field: `@output @Model` without
                // `$field` has nowhere to go.
                if ($templates && $this->schemas->has($modelName)) {
                    $this->noteMalformed($line);
                } else {
                    $this->noteUnresolved($line, $modelName);
                }

                // A reference that does not resolve still names a field: it
                // is documented as one of an unknown type, which is a string.
                if (!preg_match(DocPatterns::inputOutput(), trim($line->body), $parts)) {
                    continue;
                }
            } elseif ($kind === DocLine::TEMPLATE) {
                $this->noteUnresolved($line, (string) ($parts['template'] ?? ''));
                continue;
            } elseif ($kind !== DocLine::FIELD) {
                $this->noteMalformed($line);
                continue;
            }

            $descText = Arr::get($parts, 'description', '');
            $enum = FieldSchemas::extractEnum($descText);

            $prop = [
                'type' => FieldSchemas::safeType(Arr::get($parts, 'type', 'string')),
                'name' => Arr::get($parts, 'variable', ''),
                'description' => $descText,
                'required' => Arr::get($parts, 'optional', '') !== '?',
            ];

            $format = Arr::get($parts, 'format');
            if ($format) {
                $prop['format'] = $format;
            }
            if ($enum !== null) {
                $prop['enum'] = $enum;
            }

            $properties[$parts['variable']] = $prop;
        }

        if (FieldSchemas::hasNested(array_map(
            static fn ($variable): array => ['variable' => (string) $variable],
            array_keys($properties)
        ))) {
            // Every line takes part — the flat siblings and the roots' own
            // declarations too, not only the dotted ones; references to
            // templates are already schemas and are carried over as they are.
            $parsedParams = [];
            $references = [];
            foreach ($properties as $variable => $prop) {
                if (!isset($prop['type'])) {
                    $references[$variable] = $prop;
                    continue;
                }
                $parsedParams[] = [
                    'variable' => (string) $variable,
                    'type' => $prop['type'],
                    'format' => $prop['format'] ?? null,
                    'description' => $prop['description'] ?? '',
                    'required' => $prop['required'] ?? true,
                ];
            }

            $nested = [
                'description' => 'Response payload',
                'type' => 'object',
                'properties' => array_merge(FieldSchemas::nested($parsedParams), $references),
            ];
            $required = FieldSchemas::requiredRoots($parsedParams);
            if (!empty($required)) {
                $nested['required'] = $required;
            }

            return $nested;
        }

        $requiredFields = [];
        foreach ($properties as $variable => $prop) {
            if (!empty($prop['required'])) {
                $requiredFields[] = $variable;
            }
            unset($properties[$variable]['required']);
        }

        $schema = [
            'description' => 'Response payload',
            'type' => 'object',
            'properties' => $properties,
        ];

        if (!empty($requiredFields)) {
            $schema['required'] = $requiredFields;
        }

        return $schema;
    }

    /**
     * Calls a `[method]` on the controller, telling it which operation is
     * being described.
     *
     * The context goes through the container: a parameter typed
     * OperationContext receives it whatever its name, and `$version`,
     * `$controllerKey`, `$actionKey` and `$httpMethod` receive those scalars.
     * A method declaring none of them is called exactly as it always was.
     *
     * @return array A list of `type ?$name Description` lines, or an object schema.
     */
    private function callSchemaMethod(string $class, string $method, OperationContext $context): array
    {
        $result = app()->call("{$class}@{$method}", $context->callParameters());

        if (!is_array($result)) {
            $this->notes[] = new DescriptorNote(
                'callable.not-array',
                "[{$method}] returned " . get_debug_type($result) . ', and it is ignored: it has to return docblock lines or an object schema.'
            );

            return [];
        }

        return $result;
    }

    /**
     * The strings a `[method]` returned, as lines of the tag.
     *
     * @return DocLine[]
     */
    private function linesOf(array $result, string $tag, string $method): array
    {
        $lines = [];
        foreach ($result as $key => $value) {
            if (!is_string($value)) {
                $this->notes[] = new DescriptorNote(
                    'callable.non-string',
                    "[{$method}] returned " . get_debug_type($value) . " at [{$key}], and it is ignored: the lines have to be strings."
                );
                continue;
            }

            $line = new DocLine($tag, $value);
            $this->returnedBy[spl_object_id($line)] = $method;
            $lines[] = $line;
        }

        return $lines;
    }

    /** A line a `[method]` returned that is none of the forms its tag takes. */
    private function noteMalformed(DocLine $line): void
    {
        $method = $this->returnedBy[spl_object_id($line)] ?? null;
        if ($method === null) {
            return;
        }

        $this->notes[] = new DescriptorNote(
            'callable.malformed-line',
            "[{$method}] returned `{$line->body}` for @{$line->tag}, which does not parse, and it is dropped.",
        );
    }

    /** A template a line a `[method]` returned refers to, and which does not resolve. */
    private function noteUnresolved(DocLine $line, string $template): void
    {
        $method = $this->returnedBy[spl_object_id($line)] ?? null;
        if ($method === null) {
            return;
        }

        $why = $this->schemas->enabled()
            ? 'which is not defined'
            : 'and $useResponseTemplates is off';

        $this->notes[] = new DescriptorNote(
            'callable.unknown-template',
            "[{$method}] returned `{$line->body}` for @{$line->tag}: it refers to the template `{$template}`, {$why}.",
        );
    }
}
