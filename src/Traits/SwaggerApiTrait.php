<?php

namespace Dskripchenko\LaravelApi\Traits;

use Dskripchenko\LaravelApi\Facades\ApiModule;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\DocBlockFactory;

/**
 * Trait SwaggerApiTrait
 * @package Dskripchenko\LaravelApi\Traits
 */
trait SwaggerApiTrait
{
    public static $useResponseTemplates = false;

    protected static ?DocBlockFactory $docBlockFactory = null;

    protected static ?array $cachedRawTemplates = null;

    /**
     * @param string $version
     * @return array
     * @throws \ReflectionException
     */
    public static function getSwaggerApiConfig(string $version)
    {
        $reflectionClass = new \ReflectionClass(static::class);
        $docBlock        = static::getDocBlockByComment($reflectionClass->getDocComment());

        $config = [
            'swagger' => '2.0',
            'info' => [
                'title' => $docBlock->getSummary(),
                'description' => $docBlock->getDescription()->render(),
                'version' => $version,
            ],
            'host' => request()->getHttpHost(),
            'basePath' => '/' . ApiModule::getApiPrefix(),
            'schemes' => [
                request()->getScheme()
            ],
            'paths' => static::getSwaggerApiPaths($version),
        ];

        if (static::$useResponseTemplates) {
            $config['definitions'] = static::getSchemas();
        }

        $securityDefinitions = static::getSwaggerSecurityDefinitions();
        if (!empty($securityDefinitions)) {
            $config['securityDefinitions'] = $securityDefinitions;
        }

        return $config;
    }


    /**
     * @param string $version
     * @return array
     * @throws \ReflectionException
     */
    private static function getSwaggerApiPaths(string $version)
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

                $summary     = $docBlock->getSummary();
                $description = $declaringClass . PHP_EOL . $docBlock->getDescription()->render();
                $tags        = [$controller];
                $httpMethods  = Arr::get($actions, "{$key}.method", ['post']);
                if (!$httpMethods) {
                    $httpMethods = ['post'];
                }

                if (!is_array($httpMethods)) {
                    $httpMethods = [$httpMethods];
                }

                $actionSecurity = is_array($value) ? Arr::get($value, 'security', []) : [];
                $deprecated = static::isDeprecated($docBlock);
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

                    $consumes = static::detectConsumes($parameters);

                    $methodData = static::getMethodData($summary, $description, [
                        'tags' => $tags,
                        'parameters' => $parameters,
                        'responses' => $responses,
                        'operationId' => $operationId,
                        'deprecated' => $deprecated,
                        'security' => $security,
                        'consumes' => $consumes,
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
                                '$ref' => "#/definitions/{$modelName}",
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
                    $param['x-example'] = $defaultsAndExamples[$varName]['example'];
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
                            '$ref' => "#/definitions/{$matches['template']}"
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
                            'items' => ['$ref' => "#/definitions/{$modelName}"],
                            'description' => Arr::get($matches, 'description', ''),
                        ];
                    } else {
                        $properties[$variable] = [
                            '$ref' => "#/definitions/{$modelName}",
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

        return [
            'description' => 'Response payload',
            'type' => 'object',
            'properties' => $properties,
        ];
    }

    /**
     * @return string
     */
    private static function getDocInputOutputPattern()
    {
        return '/^(?<type>[\S]*?)(?:\((?<format>[a-zA-Z0-9\-]+)\))?[\s]*+(?<optional>\?)?\$(?<variable>[\S]*+)([\s]*?(?<description>\S[\S\s]*?))?$/';
    }

    /**
     * @return string
     */
    private static function getDocInputOutputTemplatePattern()
    {
        return '/{(?<template>[\S]*?)}(?<description>[\s\S]*?)$/';
    }

    /**
     * @return string
     */
    private static function getDocInputsCallablePattern()
    {
        return '/^\[(?<callable>[\S]*?)\]$/';
    }

    /**
     * @return string
     */
    private static function getDocModelRefPattern()
    {
        return '/^@(?<model>[\w]+)(?<isArray>\[\])?\s*(?:(?<optional>\?)?\$(?<variable>[\S]+)(?:\s+(?<description>.+))?)?$/';
    }

    /**
     * @return string
     */
    private static function getDocResponsePattern()
    {
        return '/^(?<code>\d{3})\s+(?:(?<template>\{[\S]*?\})|(?<description>.+))$/';
    }

    /**
     * @return string
     */
    private static function getDocDefaultExamplePattern()
    {
        return '/^\$(?<variable>[\S]+)\s+(?<value>.+)$/';
    }

    /**
     * @return string[]
     */
    private static function getAvailableDataTypes()
    {
        return ['string', 'file', 'number', 'integer', 'boolean', 'array', 'object'];
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
            if (isset(static::$middlewareInputTagCache[$key])) {
                $tagList = array_merge_deep($tagList, static::$middlewareInputTagCache[$key]);
                return;
            }

            $middlewareReflection = new \ReflectionClass($middleware);
            $method = 'run';
            if (!$middlewareReflection->hasMethod($method)) {
                $method = 'handle';
                if (!$middlewareReflection->hasMethod($method)) {
                    static::$middlewareInputTagCache[$key] = [];
                    return;
                }
            }

            $middlewareReflectionMethod = $middlewareReflection->getMethod($method);
            $middlewareDocBlock = static::getDocBlockByComment($middlewareReflectionMethod->getDocComment());
            $middlewareTagList = $middlewareDocBlock->getTagsByName($tagName);
            static::$middlewareInputTagCache[$key] = $middlewareTagList;
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
                        'schema' => [
                            '$ref' => "#/definitions/{$templateName}",
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
     * @param array $parameters
     * @return array
     */
    private static function detectConsumes(array $parameters): array
    {
        foreach ($parameters as $param) {
            if (($param['type'] ?? '') === 'file') {
                return ['multipart/form-data'];
            }
            if (($param['in'] ?? '') === 'body') {
                return ['application/json'];
            }
        }
        return ['application/x-www-form-urlencoded'];
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
        $parameters = $options['parameters'] ?? [];
        $responses = $options['responses'] ?? [];
        $operationId = $options['operationId'] ?? null;
        $deprecated = $options['deprecated'] ?? false;
        $security = $options['security'] ?? [];
        $consumes = $options['consumes'] ?? ['application/x-www-form-urlencoded'];
        $hasExplicitResponses = $options['hasExplicitResponses'] ?? false;

        if (!$hasExplicitResponses) {
            $responses = [
                'payload' => $responses,
            ];
        }

        if (static::$useResponseTemplates && !$hasExplicitResponses) {
            $responses = array_merge_deep($responses, [
                'success' => [
                    'description' => 'Success response',
                    'schema' => [
                        '$ref' => "#/definitions/Success"
                    ]
                ],
                'error' => [
                    'description' => 'Error response',
                    'schema' => [
                        '$ref' => "#/definitions/Error"
                    ]
                ],
            ]);
        }

        $result = [
            'summary' => $summary,
            'description' => $description,
            'tags' => $tags,
            'consumes' => $consumes,
            'parameters' => $parameters,
            'responses' => $responses,
        ];

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
        $result = [];
        foreach (static::getRawTemplates() as $schemaName => &$properties) {
            $requiredProperties = [];
            foreach ($properties as $property => &$attributes) {
                if (isset($attributes['required'])) {
                    if ($attributes['required']) {
                        $requiredProperties[] = $property;
                    }
                    unset($attributes['required']);
                }
            }

            $result[$schemaName] = [
                'type' => 'object',
                'properties' => $properties,
                'required' => $requiredProperties
            ];
        }
        return $result;
    }

    /**
     * @return array
     */
    private static function getRawTemplates()
    {
        if (static::$cachedRawTemplates !== null) {
            return static::$cachedRawTemplates;
        }

        $defaultTemplates = [
            'Error' => [
                'success' => [
                    'type' => 'boolean',
                ],
                'errorKey' => [
                    'type' => 'string',
                ],
                'message' => [
                    'type' => 'string',
                ],
            ],
            'Success' => [
                'success' => [
                    'type' => 'boolean',
                ],
                'payload' => [
                    'type' => 'object',
                    'description' => 'Response payload',
                ],
            ]
        ];

        $templates = static::getSwaggerTemplates();

        array_walk_recursive($templates, function (&$item, $key) {
            if (strpos($item, '@') !== false) {
                $item = ['$ref' => str_replace('@', '#/definitions/', $item)];
            }
        });

        static::$cachedRawTemplates = array_merge_deep($defaultTemplates, $templates);
        return static::$cachedRawTemplates;
    }

    /**
     * @param $name
     * @return bool
     */
    private static function isHasTemplate($name)
    {
        return Arr::has(static::getRawTemplates(), $name);
    }

    /**
     * @return array
     */
    protected static function getSwaggerTemplates()
    {
        return []; //override in final class
    }

    /**
     * @return array
     */
    protected static function getSwaggerSecurityDefinitions(): array
    {
        return []; //override in final class
    }
}
