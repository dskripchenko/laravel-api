<?php


namespace Dskripchenko\LaravelApi\Components;


use Dskripchenko\LaravelApi\Facades\ApiModule;
use Illuminate\Support\Arr;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\DocBlockFactory;

trait SwaggerApiTrait
{
    /**
     * @param string $version
     * @return array
     * @throws \ReflectionException
     */
    public static function getSwaggerApiConfig(string $version){
        $reflectionClass = new \ReflectionClass(static::class);
        $docBlock = static::getDocBlockByComment($reflectionClass->getDocComment());
        return [
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
            'paths' => static::getSwaggerApiPaths($version)
        ];
    }


    /**
     * @param string $version
     * @return array
     * @throws \ReflectionException
     */
    private static function getSwaggerApiPaths(string $version){
        $result = [];
        $methods = static::getPreparedMethods();
        $patternParts = explode('/', ApiModule::getApiUriPattern());
        $availableMethods = ApiModule::getAvailableApiMethods();
        foreach (Arr::get($methods, 'controllers', []) as $controller => $options){
            $class = Arr::get($options, 'controller');
            $reflectionClass = new \ReflectionClass($class);

            $actions = Arr::get($options, 'actions', []);
            foreach ($actions as $key => $value){
                if($value === false){
                    continue;
                }

                $action = $key;
                if(is_numeric($action)){
                    $action = $value;
                }
                if(!is_string($action)){
                    continue;
                }

                $methodKey = $action;
                if(is_string($value)){
                    $methodKey = $value;
                }
                if(is_array($value) && isset($value['action'])){
                    $methodKey = $value['action'];
                }


                $middlewareList = static::getMiddlewareByControllerAndActionKey($controller,$action);
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
                foreach ($availableMethods as $method){
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
    private static function getDocBlockByComment($comment){
        if(!$comment){
            $comment = ' ';
        }
        $factory = DocBlockFactory::createInstance();

        return $factory->create($comment);
    }

    /**
     * @param array $tags
     * @return array
     */
    private static function getParametersByTags(array $tags){
        $parameters = [];
        $pattern = static::getDocInputOutputPattern();
        /**
         * @var Tag $tag
         */
        foreach ($tags as $tag){
            $desctiption = $tag->getDescription()->render();

            if(preg_match($pattern, $desctiption, $matches)){
                $parameters[] = [
                    'in' => 'formData',
                    'name' => Arr::get($matches, 'variable', ''),
                    'description' => Arr::get($matches, 'description', ''),
                    'required' =>  Arr::get($matches, 'optional', '') !== '?',
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
    private static function getResponseByTags(array $tags){
        $properties = [];

        $pattern = static::getDocInputOutputPattern();
        /**
         * @var Tag $tag
         */
        foreach ($tags as $tag){
            $desctiption = $tag->getDescription()->render();

            if(preg_match($pattern, $desctiption, $matches)){
                $properties[$matches['variable']] = [
                    'type' => $matches['type'],
                    'name' => $matches['variable'],
                    'description' => $matches['description'],
                    'required' => $matches['optional'] !== '?',
                ];
            }
        }


        return [
            'description' => 'OK',
            'schema' => [
                'type' => 'object',
                'properties' => $properties,
            ]
        ];
    }

    /**
     * @return string
     */
    private static function getDocInputOutputPattern(){
        return '/^(?<type>[\S]*?)[\s]*+(?<optional>\?)?\$(?<variable>[\S]*+)([\s]*?(?<description>\S[\S\s]*?))?$/';
    }

    /**
     * @param $patternParts
     * @param $version
     * @param $controller
     * @param $action
     * @return string
     */
    private static function getApiPath($patternParts, $version, $controller, $action){
        $replaceParts = [
            '{version}' => $version,
            '{controller}' => $controller,
            '{action}' => $action,
        ];
        return '/' . implode('/', str_replace(array_keys($replaceParts),array_values($replaceParts),$patternParts));
    }

    /**
     * @param DocBlock $methodDocBlock
     * @param array $middlewareList
     * @return Tag[]
     * @throws \ReflectionException
     */
    private static function getInputTags(DocBlock $methodDocBlock, array $middlewareList = []){
        $inputTagList = [];
        $methodTagList = $methodDocBlock->getTagsByName('input');

        foreach ($middlewareList as $middleware){
            $middlewareReflection = new \ReflectionClass($middleware);
            $middlewareReflectionMethod = $middlewareReflection->getMethod('handle');
            $middlewareDocBloick = static::getDocBlockByComment($middlewareReflectionMethod->getDocComment());
            $middlewareTagList = $middlewareDocBloick->getTagsByName('input');
            $inputTagList = ArrayMergeHelper::merge($inputTagList,$middlewareTagList);
        }

        return ArrayMergeHelper::merge($inputTagList,$methodTagList);
    }

    /**
     * @param DocBlock $methodDocBlock
     * @return Tag[]
     */
    private static function getOutputTagList(DocBlock $methodDocBlock){
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
    private static function getMethodData($summary, $description, $tags = [], $parameters = [], $responses = []){
        return [
            'summary' => $summary,
            'description' => $description,
            'tags' => $tags,
            'consumes' => [
                'application/x-www-form-urlencoded'
            ],
            'parameters' => $parameters,
            'responses' => [
                '200' => $responses
            ]

        ];
    }


}
