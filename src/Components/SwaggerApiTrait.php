<?php


namespace Dskripchenko\LaravelApi\Components;


use Dskripchenko\LaravelApi\Facades\ApiModule;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\DocBlockFactory;

trait SwaggerApiTrait
{
    public static $useResponseTemplates = false;

    /**
     * @param string $version
     * @return array
     * @throws \ReflectionException
     */
    public static function getSwaggerApiConfig(string $version)
    {
        $reflectionClass = new \ReflectionClass(static::class);
        $docBlock = static::getDocBlockByComment($reflectionClass->getDocComment());

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

        return $config;
    }


    /**
     * @param string $version
     * @return array
     * @throws \ReflectionException
     */
    private static function getSwaggerApiPaths(string $version)
    {
        $result = [];
        $methods = static::getPreparedMethods();
        $patternParts = explode('/', ApiModule::getApiUriPattern());
        $availableMethods = ApiModule::getAvailableApiMethods();
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


                $middlewareList = static::getMiddlewareByControllerAndActionKey($controller, $action);
                $reflectionMethod = $reflectionClass->getMethod($methodKey);
                $docBlock = static::getDocBlockByComment($reflectionMethod->getDocComment());


                $inputTagList = static::getInputTags($docBlock, $middlewareList);
                $outputTagList = static::getOutputTagList($docBlock);

                $declaringClass = $reflectionMethod->getDeclaringClass()->name;

                $summary = $docBlock->getSummary();
                $description = $declaringClass . PHP_EOL . $docBlock->getDescription()->render();
                $tags = [$controller];
                $parameters = static::getParametersByTags($inputTagList);
                $response = static::getResponseByTags($outputTagList);

                $methodData = static::getMethodData($summary, $description, $tags, $parameters, $response);

                $path = static::getApiPath($patternParts, $version, $controller, $action);
                $result[$path] = [];
                foreach ($availableMethods as $method) {
                    $result[$path][$method] = $methodData;
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
        $factory = DocBlockFactory::createInstance();

        return $factory->create($comment);
    }

    /**
     * @param array $tags
     * @return array
     */
    private static function getParametersByTags(array $tags)
    {
        $parameters = [];
        $pattern = static::getDocInputOutputPattern();
        /**
         * @var Tag $tag
         */
        foreach ($tags as $tag) {
            $desctiption = $tag->getDescription()->render();

            if (preg_match($pattern, $desctiption, $matches)) {
                $parameters[] = [
                    'in' => 'formData',
                    'name' => Arr::get($matches, 'variable', ''),
                    'description' => Arr::get($matches, 'description', ''),
                    'required' => Arr::get($matches, 'optional', '') !== '?',
                    'type' => Arr::get($matches, 'type', 'string'),
                ];
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
        $pattern = static::getDocInputOutputPattern();
        $templatePattern = static::getDocInputOutputTemplatePattern();
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

            if (preg_match($pattern, $desctiption, $matches)) {
                $properties[$matches['variable']] = [
                    'type' => Arr::get($matches, 'type', ''),
                    'name' => Arr::get($matches, 'variable', ''),
                    'description' => Arr::get($matches, 'description', ''),
                    'required' => Arr::get($matches, 'optional', '') !== '?',
                ];
                continue;
            }
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
        return '/^(?<type>[\S]*?)[\s]*+(?<optional>\?)?\$(?<variable>[\S]*+)([\s]*?(?<description>\S[\S\s]*?))?$/';
    }

    /**
     * @return string
     */
    private static function getDocInputOutputTemplatePattern()
    {
        return '/{(?<template>[\S]*?)}(?<description>[\s\S]*?)$/';
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
    private static function getInputTags(DocBlock $methodDocBlock, array $middlewareList = [])
    {
        $inputTagList = [];
        $methodTagList = $methodDocBlock->getTagsByName('input');

        $addInputToTagList = function ($middleware, &$inputTagList) {
            $middlewareReflection = new \ReflectionClass($middleware);
            $method = 'run';
            if (!$middlewareReflection->hasMethod($method)) {
                $method = 'handle';
                if (!$middlewareReflection->hasMethod($method)) {
                    return;
                }
            }

            $middlewareReflectionMethod = $middlewareReflection->getMethod($method);
            $middlewareDocBloick = static::getDocBlockByComment($middlewareReflectionMethod->getDocComment());
            $middlewareTagList = $middlewareDocBloick->getTagsByName('input');
            $inputTagList = array_merge_deep($inputTagList, $middlewareTagList);
        };
        foreach ($middlewareList as $middleware) {
            if (class_exists($middleware)) {
                $addInputToTagList($middleware, $inputTagList);
            } else {
                foreach (Arr::get(Route::getMiddlewareGroups(), $middleware, []) as $groupedMiddleware) {
                    $addInputToTagList($groupedMiddleware, $inputTagList);
                }
            }
        }
        return array_merge_deep($inputTagList, $methodTagList);
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
     * @param $summary
     * @param $description
     * @param array $tags
     * @param array $parameters
     * @param array $responses
     * @return array
     */
    private static function getMethodData($summary, $description, $tags = [], $parameters = [], $responses = [])
    {
        $responses = [
            'payload' => $responses,
        ];

        if (static::$useResponseTemplates) {
            $responses = array_merge_deep(
                $responses,
                [
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
                ]
            );
        }

        return [
            'summary' => $summary,
            'description' => $description,
            'tags' => $tags,
            'consumes' => [
                'application/x-www-form-urlencoded'
            ],
            'parameters' => $parameters,
            'responses' => $responses,

        ];
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

        array_walk_recursive(
            $templates,
            function (&$item, $key) {
                if (strpos($item, '@') !== false) {
                    $item = ['$ref' => str_replace('@', '#/definitions/', $item)];
                }
            }
        );

        return array_merge_deep($defaultTemplates, $templates);
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


}
