<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Services\OpenApi;

use Dskripchenko\LaravelApi\Facades\ApiModule;
use Dskripchenko\LaravelApi\Operations\Descriptor\OperationDescriptor;
use Dskripchenko\LaravelApi\Operations\Descriptor\OutputDeclaration;
use Dskripchenko\LaravelApi\Operations\Descriptor\ResponseDeclaration;
use Dskripchenko\LaravelApi\Operations\DescriptorBuilder;
use Dskripchenko\LaravelApi\Operations\Doc\OperationDocParser;
use Dskripchenko\LaravelApi\Operations\OperationRegistry;
use Dskripchenko\LaravelApi\Operations\Schema\SchemaRef;
use Dskripchenko\LaravelApi\Operations\Schema\SchemaRegistry;

/**
 * Renders the operations of an API class as an OpenAPI 3.0 document.
 *
 * The operations come described already ({@see DescriptorBuilder}); what is
 * decided here is how they travel over HTTP — the paths, which input goes to
 * the query string, a form or a JSON body, the response envelope, the error
 * answer — and how OpenAPI spells all that.
 */
final class OpenApiRenderer
{
    private readonly SchemaRegistry $schemas;

    private readonly DescriptorBuilder $builder;

    /** @param class-string $apiClass */
    public function __construct(public readonly string $apiClass)
    {
        $this->schemas = SchemaRegistry::for($apiClass);
        $this->builder = new DescriptorBuilder($this->schemas);
    }

    /**
     * @param string $version
     * @return array
     * @throws \ReflectionException
     */
    public function render(string $version): array
    {
        $reflectionClass = new \ReflectionClass($this->apiClass);
        $doc             = OperationDocParser::parse($reflectionClass->getDocComment());

        $scheme   = request()->getScheme();
        $host     = request()->getHttpHost();
        $basePath = '/' . ApiModule::getApiPrefix();

        $config = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => $doc->summary,
                'description' => $doc->description,
                'version' => $version,
            ],
            'servers' => [
                ['url' => "{$scheme}://{$host}{$basePath}"],
            ],
            'paths' => $this->paths($version),
        ];

        $components = [];

        if ($this->apiClass::$useResponseTemplates) {
            $components['schemas'] = $this->schemas->all();
        }

        $securityDefinitions = $this->apiClass::getOpenApiSecurityDefinitions();
        if (!empty($securityDefinitions)) {
            $components['securitySchemes'] = $securityDefinitions;
        }

        if (!empty($components)) {
            $config['components'] = $components;
        }

        return $config;
    }

    /**
     * @param string $version
     * @return array
     * @throws \ReflectionException
     */
    public function paths(string $version): array
    {
        $result       = [];
        $patternParts = explode('/', ApiModule::getApiUriPattern());
        $builder      = $this->builder;
        foreach (OperationRegistry::for($this->apiClass, (string) $version)->controllers() as $definition) {
            // Reflected before the actions, as a controller entry: a wrong
            // class fails the spec even when none of its actions is enabled.
            new \ReflectionClass($definition->class);

            foreach ($definition->operations as $operation) {
                $path = $this->path($patternParts, $version, $operation->controllerKey, $operation->actionKey);

                foreach ($builder->build($operation) as $descriptor) {
                    $result[$path][$descriptor->httpMethod] = $this->operation($descriptor);
                }
            }
        }
        return $result;
    }

    /**
     * @param OperationDescriptor $descriptor
     * @return array
     */
    public function operation(OperationDescriptor $descriptor): array
    {
        $operation = $descriptor->operation;
        $description = $descriptor->description();

        // A controller's FQCN is a debugging detail: in a public spec
        // it exposes the internal structure of the namespaces. It is off
        // by default; whoever needs it has
        // `laravel-api.expose_controller_class`.
        if (config('laravel-api.expose_controller_class', false)) {
            $declaringClass = (new \ReflectionMethod($operation->controllerClass, $operation->controllerMethod))
                ->getDeclaringClass()->name;
            $description = trim($declaringClass . PHP_EOL . $description);
        }

        [$oasParameters, $requestBody] = $this->parametersAndBody($this->rawParameters($descriptor));

        $result = [
            'summary' => $descriptor->summary(),
            'description' => $description,
            'tags' => [$operation->controllerKey],
            'parameters' => $oasParameters,
            'responses' => $descriptor->hasExplicitResponses()
                ? $this->explicitResponses($descriptor->responses)
                : $this->responses($descriptor->output),
        ];

        if ($requestBody !== null) {
            $result['requestBody'] = $requestBody;
        }

        $result['operationId'] = $descriptor->operationId;

        if ($descriptor->deprecated) {
            $result['deprecated'] = true;
        }

        if (!empty($descriptor->security)) {
            $result['security'] = $descriptor->security;
        }

        return $result;
    }

    /**
     * The input as Swagger-2-style parameters — `in` query, formData, body or
     * header — which buildOasParametersAndBody() folds into OpenAPI 3.
     *
     * Flat lines are query parameters for GET and form fields otherwise;
     * nested lines, a `[method]` schema and `@Model` are a JSON body.
     *
     * @param OperationDescriptor $descriptor
     * @return array
     */
    private function rawParameters(OperationDescriptor $descriptor): array
    {
        $input = $descriptor->input;
        $parameterType = strtolower($descriptor->httpMethod) === 'get' ? 'query' : 'formData';

        $parameters = [];
        foreach ($input->refs as $modelName) {
            $parameters[] = [
                'in' => 'body',
                'name' => 'body',
                'description' => $modelName,
                'required' => true,
                'schema' => SchemaRef::to($modelName),
            ];
        }

        if ($input->hasSchemas()) {
            $parameters = array_merge($parameters, $this->parametersFromObjectSchema($input->mergedSchema(), $parameterType));
        } elseif ($input->isNested()) {
            $parameters[] = $this->bodyFromNested($input->nestedProperties(), $input->requiredRoots());
        } else {
            foreach ($input->fields() as $field) {
                $parameters[] = ['in' => $parameterType] + $field;
            }
        }

        $headers = array_map(static fn (array $header): array => ['in' => 'header'] + $header, $descriptor->headers);

        return array_merge($headers, $parameters);
    }

    /**
     * @param ResponseDeclaration[] $declarations
     * @return array
     */
    private function explicitResponses(array $declarations): array
    {
        $envelope = $this->schemas;
        $responses = [];

        foreach ($declarations as $declaration) {
            if ($declaration->template !== null) {
                $responses[$declaration->code] = [
                    'description' => $declaration->template,
                    'content' => [
                        'application/json' => [
                            'schema' => $envelope->wrap(SchemaRef::to($declaration->template)),
                        ],
                    ],
                ];
            } else {
                $responses[$declaration->code] = [
                    'description' => $declaration->description,
                ];
            }
        }

        return $responses;
    }

    /**
     * An object schema as parameters: the request body for anything but GET,
     * and one query parameter per top-level property for GET.
     *
     * @param  array  $schema
     * @param  string  $parameterType  `query` or `formData`
     * @return array
     */
    private function parametersFromObjectSchema(array $schema, string $parameterType): array
    {
        if (empty($schema['properties'])) {
            return [];
        }

        if ($parameterType !== 'query') {
            return [[
                'in' => 'body',
                'name' => 'body',
                'description' => 'Request body',
                'required' => true,
                'schema' => $schema,
            ]];
        }

        $required = (array) ($schema['required'] ?? []);
        $parameters = [];
        foreach ($schema['properties'] as $name => $property) {
            $property = (array) $property;
            $description = (string) ($property['description'] ?? '');
            unset($property['description']);

            $parameters[] = [
                'in' => 'query',
                'name' => (string) $name,
                'description' => $description,
                'required' => in_array($name, $required, true),
                'schema' => $property,
            ];
        }

        return $parameters;
    }

    /**
     * The path of an action: the URI pattern with its placeholders filled in.
     *
     * @param string[] $patternParts The URI pattern split on `/`.
     */
    private function path(array $patternParts, string $version, string $controller, string $action): string
    {
        $replaceParts = [
            '{version}' => $version,
            '{controller}' => $controller,
            '{action}' => $action,
        ];
        return '/' . implode('/', str_replace(array_keys($replaceParts), array_values($replaceParts), $patternParts));
    }

    /**
     * @param array $schema
     * @param string[] $required
     * @return array
     */
    private function bodyFromNested(array $schema, array $required = []): array
    {
        $bodySchema = [
            'type' => 'object',
            'properties' => $schema,
        ];
        if (!empty($required)) {
            $bodySchema['required'] = array_values($required);
        }

        return [
            'in' => 'body',
            'name' => 'body',
            'description' => 'Request body',
            'required' => true,
            'schema' => $bodySchema,
        ];
    }

    /**
     * @param array $rawParameters
     * @return array [oasParameters, requestBody|null]
     */
    private function parametersAndBody(array $rawParameters): array
    {
        $oasParams = [];
        $formDataProps = [];
        $formDataRequired = [];
        $bodySchema = null;
        $hasFile = false;

        foreach ($rawParameters as $param) {
            $in = $param['in'] ?? 'query';

            if (($in === 'query' || $in === 'header') && isset($param['schema'])) {
                // Already a schema: it came from an object schema a `[method]`
                // returned, constraints and all.
                $oasParam = [
                    'name' => $param['name'],
                    'in' => $in,
                    'description' => $param['description'] ?? '',
                    'required' => $param['required'] ?? false,
                    'schema' => $param['schema'],
                ];
                $oasParams[] = $oasParam;
            } elseif ($in === 'query' || $in === 'header') {
                $schema = ['type' => $param['type'] ?? 'string'];
                if (isset($param['format'])) {
                    $schema['format'] = $param['format'];
                }
                if (isset($param['enum'])) {
                    $schema['enum'] = $param['enum'];
                }
                if (isset($param['default'])) {
                    $schema['default'] = $param['default'];
                }

                $oasParam = [
                    'name' => $param['name'],
                    'in' => $in,
                    'description' => $param['description'] ?? '',
                    'required' => $param['required'] ?? false,
                    'schema' => $schema,
                ];

                if (isset($param['example'])) {
                    $oasParam['example'] = $param['example'];
                }

                $oasParams[] = $oasParam;
            } elseif ($in === 'body') {
                $bodySchema = $param['schema'] ?? null;
            } elseif ($in === 'formData') {
                $name = $param['name'] ?? '';
                if (($param['type'] ?? '') === 'file') {
                    $hasFile = true;
                    $formDataProps[$name] = [
                        'type' => 'string',
                        'format' => 'binary',
                        'description' => $param['description'] ?? '',
                    ];
                } else {
                    $prop = ['type' => $param['type'] ?? 'string'];
                    if (isset($param['format'])) {
                        $prop['format'] = $param['format'];
                    }
                    if (isset($param['enum'])) {
                        $prop['enum'] = $param['enum'];
                    }
                    if (isset($param['default'])) {
                        $prop['default'] = $param['default'];
                    }
                    if (isset($param['example'])) {
                        $prop['example'] = $param['example'];
                    }
                    $prop['description'] = $param['description'] ?? '';
                    $formDataProps[$name] = $prop;
                }
                if ($param['required'] ?? false) {
                    $formDataRequired[] = $name;
                }
            }
        }

        $requestBody = null;
        if ($bodySchema !== null) {
            $requestBody = [
                'required' => true,
                'content' => [
                    'application/json' => ['schema' => $bodySchema],
                ],
            ];
        } elseif (!empty($formDataProps)) {
            $contentType = $hasFile ? 'multipart/form-data' : 'application/x-www-form-urlencoded';
            $schema = ['type' => 'object', 'properties' => $formDataProps];
            if (!empty($formDataRequired)) {
                $schema['required'] = $formDataRequired;
            }
            $requestBody = [
                'required' => true,
                'content' => [
                    $contentType => ['schema' => $schema],
                ],
            ];
        }

        return [$oasParams, $requestBody];
    }

    /**
     * @param OutputDeclaration $output
     * @return array
     */
    private function responses(OutputDeclaration $output): array
    {
        $responses = [
            '200' => [
                'description' => $output->description,
                'content' => [
                    'application/json' => ['schema' => $this->schemas->wrap($output->schema)],
                ],
            ],
        ];

        if ($this->apiClass::$useResponseTemplates) {
            $responses['default'] = [
                'description' => 'Error response',
                'content' => [
                    'application/json' => [
                        'schema' => SchemaRef::to('Error'),
                    ],
                ],
            ];
        }

        return $responses;
    }
}
