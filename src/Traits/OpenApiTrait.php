<?php

namespace Dskripchenko\LaravelApi\Traits;

use Dskripchenko\LaravelApi\Facades\ApiModule;
use Dskripchenko\LaravelApi\Services\OpenApi\DocPatterns;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Tag;
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

    /** The placeholder in a custom envelope that the response schema replaces. */
    private const ENVELOPE_PAYLOAD = '{payload}';

    /**
     * The keys a hand-written schema may carry. A nested array whose keys are
     * all of these (and which names a type, a $ref, properties, items or a
     * composition) is taken as a schema and passed through; any other array
     * is a map of fields — a nested object in the shorthand.
     */
    private const SCHEMA_KEYWORDS = [
        'type', 'format', 'required', 'description', 'properties', 'items', '$ref',
        'enum', 'nullable', 'example', 'examples', 'default', 'minimum', 'maximum',
        'exclusiveMinimum', 'exclusiveMaximum', 'minLength', 'maxLength', 'pattern',
        'minItems', 'maxItems', 'uniqueItems', 'minProperties', 'maxProperties',
        'additionalProperties', 'allOf', 'oneOf', 'anyOf', 'not', 'discriminator',
        'readOnly', 'writeOnly', 'deprecated', 'title', 'multipleOf', 'xml', 'externalDocs',
    ];

    private const SCHEMA_MARKERS = ['type', '$ref', 'properties', 'items', 'allOf', 'oneOf', 'anyOf', 'enum'];

    private const SCHEMA_TYPES = ['object', 'array', 'string', 'integer', 'number', 'boolean', 'null'];

    protected static ?DocBlockFactory $docBlockFactory = null;

    /** @var array<class-string, array<string, mixed>> */
    protected static array $cachedRawTemplatesByClass = [];

    /**
     * @param string $version
     * @return array
     * @throws \ReflectionException
     */
    public static function getOpenApiConfig(string $version)
    {
        $reflectionClass = new \ReflectionClass(static::class);
        $docBlock        = static::getDocBlockByComment($reflectionClass->getDocComment());

        $scheme   = request()->getScheme();
        $host     = request()->getHttpHost();
        $basePath = '/' . ApiModule::getApiPrefix();

        $config = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => static::sanitizeDocText($docBlock->getSummary()),
                'description' => static::sanitizeDocText($docBlock->getDescription()->render()),
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
        $methods      = static::getPreparedMethods();
        $patternParts = explode('/', ApiModule::getApiUriPattern());
        foreach (Arr::get($methods, 'controllers', []) as $controller => $options) {
            $class = Arr::get($options, 'controller');
            $reflectionClass = new \ReflectionClass($class);

            $actions = Arr::get($options, 'actions', []);
            foreach ($actions as $key => $value) {
                if ($value === false) {
                    continue;
                }

                $action = $key;
                if (is_numeric($action)) {
                    $action = $value;
                }
                if (!is_string($action)) {
                    continue;
                }

                $methodKey = $action;
                if (is_string($value)) {
                    $methodKey = $value;
                }
                if (is_array($value) && isset($value['action'])) {
                    $methodKey = $value['action'];
                }


                $middlewareList   = static::getMiddlewareByControllerAndActionKey($controller, $action);
                $reflectionMethod = $reflectionClass->getMethod($methodKey);
                $docBlock = static::getDocBlockByComment($reflectionMethod->getDocComment());


                $inputTagList  = static::getInputTags($docBlock, $middlewareList);
                $outputTagList = static::getOutputTagList($docBlock);
                $headerTagList = static::getTagsByNameFromDocBlockAndMiddleware('header', $docBlock, $middlewareList);
                $responseTags  = static::getResponseTags($docBlock);
                $securityTags  = static::getSecurityTags($docBlock);
                $defaultTags   = static::getDefaultTags($docBlock);
                $exampleTags   = static::getExampleTags($docBlock);

                $declaringClass = $reflectionMethod->getDeclaringClass()->name;

                $summary     = static::sanitizeDocText($docBlock->getSummary());
                $description = static::sanitizeDocText($docBlock->getDescription()->render());

                // A controller's FQCN is a debugging detail: in a public spec
                // it exposes the internal structure of the namespaces. It is off
                // by default; whoever needs it has
                // `laravel-api.expose_controller_class`.
                if (config('laravel-api.expose_controller_class', false)) {
                    $description = trim($declaringClass . PHP_EOL . $description);
                }
                $tags        = [$controller];
                $httpMethods  = Arr::get($actions, "{$key}.method", ['post']);
                if (!$httpMethods) {
                    $httpMethods = ['post'];
                }

                if (!is_array($httpMethods)) {
                    $httpMethods = [$httpMethods];
                }

                $actionSecurity = is_array($value) ? Arr::get($value, 'security', []) : [];

                // `@deprecated` lives in a method's docblock, while one
                // controller may serve several API versions — and then only one
                // of them has to be marked deprecated. That is what the flag in
                // getMethods() is for.
                $deprecated = static::isDeprecated($docBlock)
                    || (is_array($value) && (bool) Arr::get($value, 'deprecated', false))
                    || (bool) Arr::get($options, 'deprecated', false)
                    || (bool) Arr::get($methods, 'deprecated', false);
                $operationId = static::getOperationId($controller, $action);
                $security = !empty($actionSecurity) ? $actionSecurity : static::parseSecurityTags($securityTags);
                $defaultsAndExamples = static::parseDefaultAndExampleTags($defaultTags, $exampleTags);

                foreach ($httpMethods as $httpMethod) {
                    $parameters  = static::getParametersByTags($inputTagList, $class, $httpMethod, $defaultsAndExamples);
                    $headerParameters = static::getHeaderParametersByTags($headerTagList);
                    $parameters = array_merge($headerParameters, $parameters);

                    $hasExplicitResponses = !empty($responseTags);
                    $responses = $hasExplicitResponses
                        ? static::getResponsesByTags($responseTags, $outputTagList)
                        : static::getResponseByTags($outputTagList);

                    $methodData = static::getMethodData($summary, $description, [
                        'tags' => $tags,
                        'parameters' => $parameters,
                        'responses' => $responses,
                        'operationId' => $operationId,
                        'deprecated' => $deprecated,
                        'security' => $security,
                        'hasExplicitResponses' => $hasExplicitResponses,
                    ]);
                    $path   = static::getApiPath($patternParts, $version, $controller, $action);
                    $result[$path][$httpMethod] = $methodData;
                }
            }
        }
        return $result;
    }

    /**
     * Brings a docblock's text into a shape fit for a public spec.
     *
     * phpDocumentor returns the inline tags as they are, and they travel into
     * the OpenAPI raw: `{@see \App\Foo::bar()}` reads as rubbish and
     * `{@inheritDoc}` as an empty promise. We unfold them into text and leave
     * the links as URLs.
     *
     * @param  string|null  $text
     * @return string
     */
    private static function sanitizeDocText($text)
    {
        $text = (string) $text;

        if ($text === '') {
            return '';
        }

        // {@inheritDoc} / {@inheritdoc} — there is nothing to unfold, they are removed.
        $text = preg_replace('/\{@inheritdoc}/i', '', $text);

        // {@link https://... description} → "description (https://...)", or just the URL.
        $text = preg_replace_callback(
            '/\{@link\s+(\S+)(?:\s+([^}]*))?}/i',
            static function (array $m): string {
                $url   = $m[1];
                $label = isset($m[2]) ? trim($m[2]) : '';

                return $label === '' ? $url : "{$label} ({$url})";
            },
            (string) $text
        );

        // {@see \Foo\Bar::baz()} → "Bar::baz()": a full namespace is not needed
        // in public documentation, and the short name keeps the reference's
        // meaning.
        $text = preg_replace_callback(
            '/\{@see\s+([^}\s]+)(?:\s+([^}]*))?}/i',
            static function (array $m): string {
                $label = isset($m[2]) ? trim($m[2]) : '';

                if ($label !== '') {
                    return $label;
                }

                $target = ltrim($m[1], '\\');
                $pos    = strrpos($target, '\\');

                return $pos === false ? $target : substr($target, $pos + 1);
            },
            (string) $text
        );

        return trim(preg_replace('/[ \t]+\n/', "\n", (string) $text));
    }

    /**
     * @param $comment
     * @return \phpDocumentor\Reflection\DocBlock
     */
    private static function getDocBlockByComment($comment)
    {
        if (!$comment) {
            $comment = ' ';
        }
        if (static::$docBlockFactory === null) {
            static::$docBlockFactory = DocBlockFactory::createInstance();
        }

        return static::$docBlockFactory->create($comment);
    }

    /**
     * @param  array  $tags
     * @param $class
     * @param  string  $httpMethod
     * @param  array  $defaultsAndExamples
     * @return array
     */
    private static function getParametersByTags(array $tags, $class, $httpMethod = 'post', array $defaultsAndExamples = [])
    {
        $parameters = [];
        $pattern    = static::getDocInputOutputPattern();
        $callableInputsPattern = static::getDocInputsCallablePattern();
        $modelRefPattern = static::getDocModelRefPattern();
        $httpMethod = strtolower($httpMethod);

        $parameterType = $httpMethod === 'get' ? 'query' : 'formData';

        $parsedParams = [];

        /**
         * @var Tag $tag
         */
        foreach ($tags as $tag) {
            $description  = $tag->getDescription()->render();
            $descriptions = [$description];

            if (preg_match($callableInputsPattern, $description, $matches)) {
                $callable     = "{$class}@{$matches['callable']}";
                $descriptions = app()->call($callable);
            }

            foreach ($descriptions as $description) {
                if (preg_match($modelRefPattern, $description, $matches)) {
                    $modelName = $matches['model'];
                    if (static::$useResponseTemplates && static::isHasTemplate($modelName)) {
                        $parameters[] = [
                            'in' => 'body',
                            'name' => 'body',
                            'description' => $modelName,
                            'required' => true,
                            'schema' => [
                                '$ref' => "#/components/schemas/{$modelName}",
                            ],
                        ];
                    }
                    continue;
                }

                if (preg_match($pattern, $description, $matches)) {
                    $parsedParams[] = $matches;
                }
            }
        }

        if (static::hasNestedParameters($parsedParams)) {
            $nestedSchema = static::buildNestedSchema($parsedParams);
            $parameters[] = static::getBodyParameterFromNested($nestedSchema);
        } else {
            foreach ($parsedParams as $matches) {
                $descText = Arr::get($matches, 'description', '');
                $enum = static::extractEnumFromDescription($descText);
                $type = static::getSafeDataType(Arr::get($matches, 'type', 'string'));
                $varName = Arr::get($matches, 'variable', '');

                $param = [
                    'in' => $parameterType,
                    'name' => $varName,
                    'description' => $descText,
                    'required' => Arr::get($matches, 'optional', '') !== '?',
                    'type' => $type,
                ];

                $format = Arr::get($matches, 'format');
                if ($format) {
                    $param['format'] = $format;
                }
                if ($enum !== null) {
                    $param['enum'] = $enum;
                }
                if (isset($defaultsAndExamples[$varName]['default'])) {
                    $param['default'] = $defaultsAndExamples[$varName]['default'];
                }
                if (isset($defaultsAndExamples[$varName]['example'])) {
                    $param['example'] = $defaultsAndExamples[$varName]['example'];
                }

                $parameters[] = $param;
            }
        }

        return $parameters;
    }

    /**
     * @param array $tags
     * @return array
     */
    private static function getResponseByTags(array $tags)
    {
        $properties = [];
        $pattern    = static::getDocInputOutputPattern();
        $templatePattern = static::getDocInputOutputTemplatePattern();
        $modelRefPattern = static::getDocModelRefPattern();
        /**
         * @var Tag $tag
         */
        foreach ($tags as $tag) {
            $desctiption = $tag->getDescription()->render();

            if (static::$useResponseTemplates && preg_match($templatePattern, $desctiption, $matches)) {
                if (static::isHasTemplate($matches['template'])) {
                    return [
                        'description' => 'Response payload',
                        'schema' => [
                            '$ref' => "#/components/schemas/{$matches['template']}"
                        ],
                    ];
                }
            }

            if (static::$useResponseTemplates && preg_match($modelRefPattern, $desctiption, $matches)) {
                $modelName = $matches['model'];
                $isArray = !empty($matches['isArray']);
                $variable = Arr::get($matches, 'variable', '');

                if (static::isHasTemplate($modelName) && $variable) {
                    if ($isArray) {
                        $properties[$variable] = [
                            'type' => 'array',
                            'items' => ['$ref' => "#/components/schemas/{$modelName}"],
                            'description' => Arr::get($matches, 'description', ''),
                        ];
                    } else {
                        $properties[$variable] = [
                            '$ref' => "#/components/schemas/{$modelName}",
                            'description' => Arr::get($matches, 'description', ''),
                        ];
                    }
                    continue;
                }
            }

            if (preg_match($pattern, $desctiption, $matches)) {
                $descText = Arr::get($matches, 'description', '');
                $enum = static::extractEnumFromDescription($descText);

                $prop = [
                    'type' => static::getSafeDataType(Arr::get($matches, 'type', 'string')),
                    'name' => Arr::get($matches, 'variable', ''),
                    'description' => $descText,
                    'required' => Arr::get($matches, 'optional', '') !== '?',
                ];

                $format = Arr::get($matches, 'format');
                if ($format) {
                    $prop['format'] = $format;
                }
                if ($enum !== null) {
                    $prop['enum'] = $enum;
                }

                $properties[$matches['variable']] = $prop;
                continue;
            }
        }

        $parsedParams = [];
        foreach ($properties as $variable => $prop) {
            if (str_contains($variable, '.') || str_contains($variable, '[]')) {
                $parsedParams[] = [
                    'variable' => $variable,
                    'type' => $prop['type'],
                    'description' => $prop['description'] ?? '',
                    'required' => $prop['required'] ?? true,
                ];
            }
        }

        if (!empty($parsedParams) && static::hasNestedParameters($parsedParams)) {
            $nestedSchema = static::buildNestedSchema($parsedParams);
            return [
                'description' => 'Response payload',
                'type' => 'object',
                'properties' => $nestedSchema,
            ];
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
     * @return string
     */
    private static function getDocInputOutputPattern()
    {
        return DocPatterns::inputOutput();
    }

    /**
     * @return string
     */
    private static function getDocInputOutputTemplatePattern()
    {
        return DocPatterns::inputOutputTemplate();
    }

    /**
     * @return string
     */
    private static function getDocInputsCallablePattern()
    {
        return DocPatterns::inputsCallable();
    }

    /**
     * @return string
     */
    private static function getDocModelRefPattern()
    {
        return DocPatterns::modelRef();
    }

    /**
     * @return string
     */
    private static function getDocResponsePattern()
    {
        return DocPatterns::response();
    }

    /**
     * @return string
     */
    private static function getDocDefaultExamplePattern()
    {
        return DocPatterns::defaultExample();
    }

    /**
     * @return string[]
     */
    private static function getAvailableDataTypes()
    {
        return DocPatterns::availableDataTypes();
    }

    /**
     * @param $type
     * @return mixed|string
     */
    private static function getSafeDataType(string $type)
    {
        if (!in_array($type, static::getAvailableDataTypes())) {
            $type = 'string';
        }
        return $type;
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
     * @param DocBlock $methodDocBlock
     * @param array $middlewareList
     * @return Tag[]
     * @throws \ReflectionException
     */

    /** @var array<class-string, array<string, array>> */
    protected static array $middlewareInputTagCache = [];

    private static function getInputTags(DocBlock $methodDocBlock, array $middlewareList = [])
    {
        return static::getTagsByNameFromDocBlockAndMiddleware('input', $methodDocBlock, $middlewareList);
    }

    /**
     * @param string $tagName
     * @param DocBlock $methodDocBlock
     * @param array $middlewareList
     * @return Tag[]
     * @throws \ReflectionException
     */
    private static function getTagsByNameFromDocBlockAndMiddleware(
        string $tagName,
        DocBlock $methodDocBlock,
        array $middlewareList = []
    ) {
        $tagList = [];
        $methodTagList = $methodDocBlock->getTagsByName($tagName);
        $cacheKey = $tagName;

        $addTagsFromMiddleware = function ($middleware, &$tagList) use ($tagName, $cacheKey) {
            $key = "{$cacheKey}:{$middleware}";
            $classCache = &static::$middlewareInputTagCache[static::class];
            if (! isset($classCache)) {
                $classCache = [];
            }
            if (isset($classCache[$key])) {
                $tagList = array_merge_deep($tagList, $classCache[$key]);
                return;
            }

            $middlewareReflection = new \ReflectionClass($middleware);
            $method = 'run';
            if (!$middlewareReflection->hasMethod($method)) {
                $method = 'handle';
                if (!$middlewareReflection->hasMethod($method)) {
                    $classCache[$key] = [];
                    return;
                }
            }

            $middlewareReflectionMethod = $middlewareReflection->getMethod($method);
            $middlewareDocBlock = static::getDocBlockByComment($middlewareReflectionMethod->getDocComment());
            $middlewareTagList = $middlewareDocBlock->getTagsByName($tagName);
            $classCache[$key] = $middlewareTagList;
            $tagList = array_merge_deep($tagList, $middlewareTagList);
        };

        foreach ($middlewareList as $middleware) {
            if (class_exists($middleware)) {
                $addTagsFromMiddleware($middleware, $tagList);
            } else {
                foreach (Arr::get(Route::getMiddlewareGroups(), $middleware, []) as $groupedMiddleware) {
                    $addTagsFromMiddleware($groupedMiddleware, $tagList);
                }
            }
        }

        return array_merge_deep($tagList, $methodTagList);
    }

    /**
     * @param DocBlock $methodDocBlock
     * @return Tag[]
     */
    private static function getOutputTagList(DocBlock $methodDocBlock)
    {
        return $methodDocBlock->getTagsByName('output');
    }

    /**
     * @param DocBlock $docBlock
     * @return Tag[]
     */
    private static function getResponseTags(DocBlock $docBlock)
    {
        return $docBlock->getTagsByName('response');
    }

    /**
     * @param DocBlock $docBlock
     * @return Tag[]
     */
    private static function getSecurityTags(DocBlock $docBlock)
    {
        return $docBlock->getTagsByName('security');
    }

    /**
     * @param DocBlock $docBlock
     * @return Tag[]
     */
    private static function getDefaultTags(DocBlock $docBlock)
    {
        return $docBlock->getTagsByName('default');
    }

    /**
     * @param DocBlock $docBlock
     * @return Tag[]
     */
    private static function getExampleTags(DocBlock $docBlock)
    {
        return $docBlock->getTagsByName('example');
    }

    /**
     * @param DocBlock $docBlock
     * @return bool
     */
    private static function isDeprecated(DocBlock $docBlock): bool
    {
        return !empty($docBlock->getTagsByName('deprecated'));
    }

    /**
     * @param string $controllerKey
     * @param string $actionKey
     * @return string
     */
    private static function getOperationId(string $controllerKey, string $actionKey): string
    {
        return "{$controllerKey}_{$actionKey}";
    }

    /**
     * @param string $description
     * @return array|null
     */
    private static function extractEnumFromDescription(string &$description): ?array
    {
        if (preg_match('/\[([a-zA-Z0-9_,\-\s]+)\]\s*$/', $description, $matches)) {
            $description = trim(preg_replace('/\[([a-zA-Z0-9_,\-\s]+)\]\s*$/', '', $description));
            return array_map('trim', explode(',', $matches[1]));
        }
        return null;
    }

    /**
     * @param array $headerTags
     * @return array
     */
    private static function getHeaderParametersByTags(array $headerTags): array
    {
        $parameters = [];
        $pattern = static::getDocInputOutputPattern();

        foreach ($headerTags as $tag) {
            $description = $tag->getDescription()->render();
            if (preg_match($pattern, $description, $matches)) {
                $parameters[] = [
                    'in' => 'header',
                    'name' => Arr::get($matches, 'variable', ''),
                    'description' => Arr::get($matches, 'description', ''),
                    'required' => Arr::get($matches, 'optional', '') !== '?',
                    'type' => static::getSafeDataType(Arr::get($matches, 'type', 'string')),
                ];
            }
        }

        return $parameters;
    }

    /**
     * @param array $responseTags
     * @param array $outputTags
     * @return array
     */
    private static function getResponsesByTags(array $responseTags, array $outputTags): array
    {
        $responses = [];
        $pattern = static::getDocResponsePattern();

        foreach ($responseTags as $tag) {
            $description = $tag->getDescription()->render();
            if (preg_match($pattern, $description, $matches)) {
                $code = $matches['code'];
                $template = Arr::get($matches, 'template', '');

                if ($template) {
                    $templateName = trim($template, '{}');
                    $responses[$code] = [
                        'description' => $templateName,
                        'content' => [
                            'application/json' => [
                                'schema' => static::wrapInResponseEnvelope([
                                    '$ref' => "#/components/schemas/{$templateName}",
                                ]),
                            ],
                        ],
                    ];
                } else {
                    $responses[$code] = [
                        'description' => Arr::get($matches, 'description', ''),
                    ];
                }
            }
        }

        return $responses;
    }

    /**
     * @param array $securityTags
     * @return array
     */
    private static function parseSecurityTags(array $securityTags): array
    {
        $security = [];
        foreach ($securityTags as $tag) {
            $name = trim($tag->getDescription()->render());
            if ($name) {
                $security[] = [$name => []];
            }
        }
        return $security;
    }

    /**
     * @param array $defaultTags
     * @param array $exampleTags
     * @return array
     */
    private static function parseDefaultAndExampleTags(array $defaultTags, array $exampleTags): array
    {
        $result = [];
        $pattern = static::getDocDefaultExamplePattern();

        foreach ($defaultTags as $tag) {
            $desc = $tag->getDescription()->render();
            if (preg_match($pattern, $desc, $matches)) {
                $result[$matches['variable']]['default'] = static::castTagValue($matches['value']);
            }
        }

        foreach ($exampleTags as $tag) {
            $desc = $tag->getDescription()->render();
            if (preg_match($pattern, $desc, $matches)) {
                $result[$matches['variable']]['example'] = static::castTagValue($matches['value']);
            }
        }

        return $result;
    }

    /**
     * @param string $value
     * @return mixed
     */
    private static function castTagValue(string $value)
    {
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }
        if ($value === 'null') {
            return null;
        }
        return $value;
    }

    /**
     * @param array $parsedParams
     * @return bool
     */
    private static function hasNestedParameters(array $parsedParams): bool
    {
        foreach ($parsedParams as $param) {
            $variable = is_array($param) ? ($param['variable'] ?? '') : '';
            if (str_contains($variable, '.') || str_contains($variable, '[]')) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array $parsedParams
     * @return array
     */
    private static function buildNestedSchema(array $parsedParams): array
    {
        $groups = [];
        foreach ($parsedParams as $param) {
            $variable = $param['variable'] ?? '';
            $type = static::getSafeDataType($param['type'] ?? 'string');
            $description = $param['description'] ?? '';

            $parts = preg_split('/\./', str_replace('[]', '', $variable));
            $root = $parts[0];

            if (count($parts) === 1 && !str_contains($variable, '[]')) {
                $groups[$root] = ['type' => $type, 'description' => $description];
            } else {
                if (!isset($groups[$root])) {
                    $groups[$root] = ['type' => 'object', 'properties' => []];
                }
                if (count($parts) > 1) {
                    $child = $parts[1];
                    $groups[$root]['properties'][$child] = ['type' => $type, 'description' => $description];
                }
                if (str_contains($variable, '[]')) {
                    $groups[$root]['type'] = 'array';
                }
            }
        }

        return static::buildNestedSchemaRecursive($groups);
    }

    /**
     * @param array $groups
     * @return array
     */
    private static function buildNestedSchemaRecursive(array $groups): array
    {
        $result = [];
        foreach ($groups as $key => $spec) {
            $type = $spec['type'] ?? 'object';
            $description = $spec['description'] ?? '';

            if ($type === 'array' && isset($spec['properties'])) {
                $result[$key] = [
                    'type' => 'array',
                    'description' => $description,
                    'items' => [
                        'type' => 'object',
                        'properties' => $spec['properties'],
                    ],
                ];
            } elseif ($type === 'object' && isset($spec['properties'])) {
                $result[$key] = [
                    'type' => 'object',
                    'description' => $description,
                    'properties' => $spec['properties'],
                ];
            } else {
                $result[$key] = ['type' => $type, 'description' => $description];
            }
        }
        return $result;
    }

    /**
     * @param array $schema
     * @return array
     */
    private static function getBodyParameterFromNested(array $schema): array
    {
        return [
            'in' => 'body',
            'name' => 'body',
            'description' => 'Request body',
            'required' => true,
            'schema' => [
                'type' => 'object',
                'properties' => $schema,
            ],
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

            if ($in === 'query' || $in === 'header') {
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
     * @param array $responseData
     * @return array
     */
    private static function buildOasResponses(array $responseData): array
    {
        $description = $responseData['description'] ?? 'Response payload';

        if (isset($responseData['schema'])) {
            $schema = $responseData['schema'];
        } else {
            $schema = [];
            if (isset($responseData['type'])) {
                $schema['type'] = $responseData['type'];
            }
            if (isset($responseData['properties'])) {
                $schema['properties'] = $responseData['properties'];
            }
            if (isset($responseData['required'])) {
                $schema['required'] = $responseData['required'];
            }
        }

        $responses = [
            '200' => [
                'description' => $description,
                'content' => [
                    'application/json' => ['schema' => static::wrapInResponseEnvelope($schema)],
                ],
            ],
        ];

        if (static::$useResponseTemplates) {
            $responses['default'] = [
                'description' => 'Error response',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/Error'],
                    ],
                ],
            ];
        }

        return $responses;
    }

    /**
     * @param $summary
     * @param $description
     * @param array $options
     * @return array
     */
    private static function getMethodData($summary, $description, array $options = [])
    {
        $tags = $options['tags'] ?? [];
        $rawParameters = $options['parameters'] ?? [];
        $responses = $options['responses'] ?? [];
        $operationId = $options['operationId'] ?? null;
        $deprecated = $options['deprecated'] ?? false;
        $security = $options['security'] ?? [];
        $hasExplicitResponses = $options['hasExplicitResponses'] ?? false;

        [$oasParameters, $requestBody] = static::buildOasParametersAndBody($rawParameters);

        if ($hasExplicitResponses) {
            $formattedResponses = $responses;
        } else {
            $formattedResponses = static::buildOasResponses($responses);
        }

        $result = [
            'summary' => $summary,
            'description' => $description,
            'tags' => $tags,
            'parameters' => $oasParameters,
            'responses' => $formattedResponses,
        ];

        if ($requestBody !== null) {
            $result['requestBody'] = $requestBody;
        }

        if ($operationId !== null) {
            $result['operationId'] = $operationId;
        }

        if ($deprecated) {
            $result['deprecated'] = true;
        }

        if (!empty($security)) {
            $result['security'] = $security;
        }

        return $result;
    }

    /**
     * @return array
     */
    private static function getSchemas()
    {
        return static::getRawTemplates();
    }

    /**
     * Every template as a full object schema, the shorthand unfolded and the
     * two the package always provides merged in.
     *
     * @return array<string, array>
     */
    private static function getRawTemplates()
    {
        if (isset(static::$cachedRawTemplatesByClass[static::class])) {
            return static::$cachedRawTemplatesByClass[static::class];
        }

        $templates = [];
        foreach ((array) static::getOpenApiTemplates() as $name => $fields) {
            $templates[(string) $name] = static::normalizeTemplateFields(is_array($fields) ? $fields : [], true);
        }

        $errorFields = [
            'errorKey' => ['type' => 'string'],
            'message' => ['type' => 'string'],
        ];

        if (static::hasResponseEnvelope()) {
            // Every template describes a payload here, the two defaults too:
            // an own `Error` or `Success` is the payload of that answer, and
            // the envelope comes from $responseEnvelope like everywhere else.
            $errorPayload = $templates['Error']
                ?? static::normalizeTemplateFields($errorFields, true);
            $successPayload = $templates['Success']
                ?? ['type' => 'object', 'description' => 'Response payload'];

            $templates['Error'] = static::wrapInResponseEnvelope($errorPayload);
            $templates['Success'] = static::wrapInResponseEnvelope($successPayload);
        } else {
            $defaults = [
                'Error' => static::normalizeTemplateFields(['success' => ['type' => 'boolean']] + $errorFields, true),
                'Success' => static::normalizeTemplateFields([
                    'success' => ['type' => 'boolean'],
                    'payload' => ['type' => 'object', 'description' => 'Response payload'],
                ], true),
            ];

            // An own `Error` or `Success` adds to the default rather than
            // replacing it — the historical merge, kept as it was.
            foreach ($defaults as $name => $default) {
                if (!isset($templates[$name])) {
                    $templates[$name] = $default;
                    continue;
                }
                $templates[$name]['properties'] = array_merge($default['properties'], $templates[$name]['properties']);
                $templates[$name]['required'] = array_values(array_unique(array_merge(
                    $default['required'],
                    $templates[$name]['required']
                )));
            }
        }

        static::$cachedRawTemplatesByClass[static::class] = $templates;
        return static::$cachedRawTemplatesByClass[static::class];
    }

    /**
     * @param $name
     * @return bool
     */
    private static function isHasTemplate($name)
    {
        return array_key_exists((string) $name, static::getRawTemplates());
    }

    /**
     * @return bool
     */
    private static function hasResponseEnvelope(): bool
    {
        return static::getResponseEnvelopeFields() !== null;
    }

    /**
     * The envelope as a map of fields in the template shorthand, or null when
     * there is none.
     *
     * @return array<string, mixed>|null
     */
    private static function getResponseEnvelopeFields(): ?array
    {
        $envelope = static::$responseEnvelope;

        if ($envelope === true) {
            return ['success!' => 'boolean', 'payload!' => self::ENVELOPE_PAYLOAD];
        }

        if (is_array($envelope) && $envelope !== []) {
            return $envelope;
        }

        return null;
    }

    /**
     * Wraps a body schema in the response envelope; the schema itself when
     * there is no envelope.
     *
     * @param array $schema
     * @return array
     */
    private static function wrapInResponseEnvelope(array $schema): array
    {
        $fields = static::getResponseEnvelopeFields();

        if ($fields === null) {
            return $schema;
        }

        $envelope = static::normalizeTemplateFields($fields, true);
        $envelope['properties'] = static::replaceEnvelopePayload($envelope['properties'], $schema);

        return $envelope;
    }

    /**
     * Puts the body schema where the envelope says `{payload}` — at any depth,
     * so a payload under `data.result` is as good as one at the top.
     *
     * @param array $properties
     * @param array $schema
     * @return array
     */
    private static function replaceEnvelopePayload(array $properties, array $schema): array
    {
        foreach ($properties as $name => $definition) {
            if (!is_array($definition)) {
                continue;
            }

            if (($definition['type'] ?? null) === self::ENVELOPE_PAYLOAD) {
                $properties[$name] = $schema;
                continue;
            }

            if (isset($definition['properties']) && is_array($definition['properties'])) {
                $properties[$name]['properties'] = static::replaceEnvelopePayload($definition['properties'], $schema);
            }
        }

        return $properties;
    }

    /**
     * Unfolds a map of fields into an object schema: the shorthand strings,
     * the nested maps, the one-element lists and the hand-written schemas
     * alike, at any depth.
     *
     * A key ending in `!` marks the field required whatever its definition —
     * the way to require a nested object or an array, which have no string
     * to carry the mark.
     *
     * @param array $fields
     * @param bool $keepEmptyRequired  emit `required: []` even when nothing is required
     * @return array
     */
    private static function normalizeTemplateFields(array $fields, bool $keepEmptyRequired = false): array
    {
        $properties = [];
        $required = [];

        foreach ($fields as $name => $definition) {
            $name = (string) $name;
            $forced = false;

            if (str_ends_with($name, '!')) {
                $name = substr($name, 0, -1);
                $forced = true;
            }

            [$schema, $isRequired] = static::normalizeTemplateDefinition($definition);
            $properties[$name] = $schema;

            if ($forced || $isRequired) {
                $required[] = $name;
            }
        }

        $result = ['type' => 'object', 'properties' => $properties];

        if ($required !== [] || $keepEmptyRequired) {
            $result['required'] = array_values(array_unique($required));
        }

        return $result;
    }

    /**
     * One field's definition as a schema, plus whether the field is required.
     *
     *   'string!'                      → a shorthand string
     *   ['url' => 'string']            → a nested object
     *   ['string']                     → an array of strings
     *   [['id' => 'integer!']]         → an array of objects
     *   ['type' => 'string', ...]      → a hand-written schema, passed through
     *
     * @param mixed $definition
     * @return array{0: array, 1: bool}
     */
    private static function normalizeTemplateDefinition($definition): array
    {
        if (is_string($definition)) {
            $schema = static::parseShorthandType($definition);
            $required = (bool) ($schema['required'] ?? false);
            unset($schema['required']);

            return [$schema, $required];
        }

        if (!is_array($definition)) {
            return [['type' => 'string'], false];
        }

        if ($definition === []) {
            return [['type' => 'object'], false];
        }

        // A one-element list is an array of that element.
        if (array_is_list($definition) && count($definition) === 1) {
            [$items] = static::normalizeTemplateDefinition($definition[0]);

            return [['type' => 'array', 'items' => $items], false];
        }

        if (static::isExplicitSchema($definition)) {
            $required = false;

            if (isset($definition['required']) && is_bool($definition['required'])) {
                $required = $definition['required'];
                unset($definition['required']);
            }

            if (isset($definition['properties']) && is_array($definition['properties'])) {
                $nested = static::normalizeTemplateFields($definition['properties']);
                $definition['properties'] = $nested['properties'];

                $requiredList = array_merge(
                    is_array($definition['required'] ?? null) ? $definition['required'] : [],
                    $nested['required'] ?? []
                );
                if ($requiredList !== []) {
                    $definition['required'] = array_values(array_unique($requiredList));
                }
            }

            if (isset($definition['items']) && (is_string($definition['items']) || is_array($definition['items']))) {
                [$definition['items']] = static::normalizeTemplateDefinition($definition['items']);
            }

            if (isset($definition['$ref']) && is_string($definition['$ref']) && str_starts_with($definition['$ref'], '@')) {
                $definition['$ref'] = '#/components/schemas/' . substr($definition['$ref'], 1);
            }

            return [$definition, $required];
        }

        return [static::normalizeTemplateFields($definition), false];
    }

    /**
     * Whether an array is a hand-written schema rather than a map of fields.
     *
     * A map whose every key happens to be a schema keyword and whose `type`
     * names a real type — `['type' => 'string', 'format' => 'string']`, two
     * optional fields called type and format — reads as a schema; write such
     * a map with `['type' => ['type' => 'string'], ...]` to disambiguate.
     *
     * @param array $definition
     * @return bool
     */
    private static function isExplicitSchema(array $definition): bool
    {
        if (array_is_list($definition)) {
            return false;
        }

        $hasMarker = false;

        foreach ($definition as $key => $value) {
            $key = (string) $key;

            if (!in_array($key, self::SCHEMA_KEYWORDS, true) && !str_starts_with($key, 'x-')) {
                return false;
            }

            if (in_array($key, self::SCHEMA_MARKERS, true)) {
                $hasMarker = true;
            }
        }

        if (!$hasMarker) {
            return false;
        }

        if (array_key_exists('type', $definition)) {
            $type = $definition['type'];

            if (!is_string($type) || !in_array($type, self::SCHEMA_TYPES, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parses shorthand type string into a full attribute array.
     *
     * Format: "type(format)! Description" — the format, the required mark and
     * the description are all optional.
     * Examples: "integer!", "string(date-time)", "string(email)! Contact email",
     * "string Document identifier".
     * Ref syntax "@ModelName", "@ModelName!" and "@ModelName[]" resolve to $ref.
     *
     * @param string $shorthand
     * @return array
     */
    private static function parseShorthandType(string $shorthand): array
    {
        $shorthand = trim($shorthand);

        // Everything past the first space is a human description: response
        // schemas were type-only, so consumers of the spec (and anything
        // reading it, from SDK generators to search) saw field names without
        // a hint of what they mean.
        $description = null;
        if (preg_match('/^(\S+)\s+(.+)$/u', $shorthand, $parts) === 1) {
            $shorthand = $parts[1];
            $description = trim($parts[2]);
        }

        $required = false;
        if (str_ends_with($shorthand, '!')) {
            $required = true;
            $shorthand = substr($shorthand, 0, -1);
        }

        // @ref array syntax: @ModelName[] with an optional description.
        // A description next to `$ref` is ignored by OpenAPI 3.0, but an
        // array wrapping a ref is a plain object — so here it survives.
        if (preg_match('/^@(\S+?)\[\]$/u', $shorthand, $m)) {
            $result = [
                'type' => 'array',
                'items' => ['$ref' => '#/components/schemas/' . $m[1]],
            ];

            if ($description !== null && $description !== '') {
                $result['description'] = $description;
            }

            if ($required) {
                $result['required'] = true;
            }

            return $result;
        }

        // @ref syntax: @ModelName
        if (str_starts_with($shorthand, '@')) {
            $result = ['$ref' => '#/components/schemas/' . substr($shorthand, 1)];

            if ($required) {
                $result['required'] = true;
            }

            return $result;
        }

        $format = null;
        if (preg_match('/^(\w+)\(([^)]+)\)$/', $shorthand, $m)) {
            $shorthand = $m[1];
            $format = $m[2];
        }

        $result = ['type' => $shorthand];

        if ($format !== null) {
            $result['format'] = $format;
        }

        if ($required) {
            $result['required'] = true;
        }

        if ($description !== null && $description !== '') {
            $result['description'] = $description;
        }

        return $result;
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
