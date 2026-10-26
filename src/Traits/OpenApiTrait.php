<?php

namespace Dskripchenko\LaravelApi\Traits;

use Dskripchenko\LaravelApi\Facades\ApiModule;
use Dskripchenko\LaravelApi\Operations\Descriptor\OperationDescriptor;
use Dskripchenko\LaravelApi\Operations\Descriptor\OutputDeclaration;
use Dskripchenko\LaravelApi\Operations\Descriptor\ResponseDeclaration;
use Dskripchenko\LaravelApi\Operations\DescriptorBuilder;
use Dskripchenko\LaravelApi\Operations\Doc\OperationDocParser;
use Dskripchenko\LaravelApi\Operations\OperationRegistry;
use Dskripchenko\LaravelApi\Operations\Schema\SchemaRef;
use Dskripchenko\LaravelApi\Operations\Schema\SchemaRegistry;
use Illuminate\Support\Arr;
use phpDocumentor\Reflection\DocBlockFactory;

/**
 * Trait OpenApiTrait
 * @package Dskripchenko\LaravelApi\Traits
 */
trait OpenApiTrait
{
    public static $useResponseTemplates = false;

    /**
     * The envelope every JSON body travels in — what ApiResponseHelper::say()
     * really returns, so the spec can stop describing the payload as if it
     * were the whole response.
     *
     * `false` documents the payload alone (the historical behaviour). `true`
     * documents `{success: boolean, payload: <schema>}`, the package's own
     * envelope. An array is a custom envelope in the template shorthand, with
     * the string `'{payload}'` marking where the response schema goes:
     *
     *   ['success!' => 'boolean', 'data!' => '{payload}', 'meta' => '@Meta']
     *
     * With the envelope on, every template — the default `Error` and
     * `Success` included — describes a payload, and every response that has a
     * schema is wrapped: the 2xx ones and the error ones alike, because the
     * runtime wraps them all.
     *
     * @var bool|array<string, mixed>
     */
    public static $responseEnvelope = false;

    /** @deprecated 6.1 Unused: docblocks are read by OperationDocParser. Removed in 7.0. */
    protected static ?DocBlockFactory $docBlockFactory = null;

    /**
     * @deprecated 6.1 Unused: the templates are cached by SchemaRegistry. Removed in 7.0.
     * @var array<class-string, array<string, mixed>>
     */
    protected static array $cachedRawTemplatesByClass = [];

    /**
     * @param string $version
     * @return array
     * @throws \ReflectionException
     */
    public static function getOpenApiConfig(string $version)
    {
        $reflectionClass = new \ReflectionClass(static::class);
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
            'paths' => static::getOpenApiPaths($version),
        ];

        $components = [];

        if (static::$useResponseTemplates) {
            $components['schemas'] = static::getSchemas();
        }

        $securityDefinitions = static::getOpenApiSecurityDefinitions();
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
    private static function getOpenApiPaths(string $version)
    {
        $result       = [];
        $patternParts = explode('/', ApiModule::getApiUriPattern());
        $builder      = DescriptorBuilder::for(static::class);
        foreach (OperationRegistry::for(static::class, (string) $version)->controllers() as $definition) {
            // Reflected before the actions, as a controller entry: a wrong
            // class fails the spec even when none of its actions is enabled.
            new \ReflectionClass($definition->class);

            foreach ($definition->operations as $operation) {
                $path = static::getApiPath($patternParts, $version, $operation->controllerKey, $operation->actionKey);

                foreach ($builder->build($operation) as $descriptor) {
                    $result[$path][$descriptor->httpMethod] = static::getMethodData($descriptor);
                }
            }
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
    private static function getRawParameters(OperationDescriptor $descriptor): array
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
            $parameters = array_merge($parameters, static::parametersFromObjectSchema($input->mergedSchema(), $parameterType));
        } elseif ($input->isNested()) {
            $parameters[] = static::getBodyParameterFromNested($input->nestedProperties(), $input->requiredRoots());
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
    private static function getResponsesByDeclarations(array $declarations): array
    {
        $envelope = SchemaRegistry::for(static::class);
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
    private static function parametersFromObjectSchema(array $schema, string $parameterType): array
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
     * @param $patternParts
     * @param $version
     * @param $controller
     * @param $action
     * @return string
     */
    private static function getApiPath($patternParts, $version, $controller, $action)
    {
        $replaceParts = [
            '{version}' => $version,
            '{controller}' => $controller,
            '{action}' => $action,
        ];
        return '/' . implode('/', str_replace(array_keys($replaceParts), array_values($replaceParts), $patternParts));
    }

    /**
     * @deprecated 6.1 Unused: the middleware tags are cached by OperationDocParser. Removed in 7.0.
     * @var array<class-string, array<string, array>>
     */
    protected static array $middlewareInputTagCache = [];

    /**
     * @param array $schema
     * @param string[] $required
     * @return array
     */
    private static function getBodyParameterFromNested(array $schema, array $required = []): array
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
    private static function buildOasParametersAndBody(array $rawParameters): array
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
    private static function buildOasResponses(OutputDeclaration $output): array
    {
        $responses = [
            '200' => [
                'description' => $output->description,
                'content' => [
                    'application/json' => ['schema' => SchemaRegistry::for(static::class)->wrap($output->schema)],
                ],
            ],
        ];

        if (static::$useResponseTemplates) {
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

    /**
     * @param OperationDescriptor $descriptor
     * @return array
     */
    private static function getMethodData(OperationDescriptor $descriptor)
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

        [$oasParameters, $requestBody] = static::buildOasParametersAndBody(static::getRawParameters($descriptor));

        $result = [
            'summary' => $descriptor->summary(),
            'description' => $description,
            'tags' => [$operation->controllerKey],
            'parameters' => $oasParameters,
            'responses' => $descriptor->hasExplicitResponses()
                ? static::getResponsesByDeclarations($descriptor->responses)
                : static::buildOasResponses($descriptor->output),
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
     * @return array
     */
    private static function getSchemas()
    {
        return SchemaRegistry::for(static::class)->all();
    }

    /**
     * @return array
     */
    protected static function getOpenApiTemplates()
    {
        return []; //override in final class
    }

    /**
     * @return array
     */
    protected static function getOpenApiSecurityDefinitions(): array
    {
        return []; //override in final class
    }
}
