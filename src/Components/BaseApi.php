<?php

namespace Dskripchenko\LaravelApi\Components;

use Dskripchenko\LaravelApi\Facades\ApiRequest;
use Dskripchenko\LaravelApi\Facades\ApiErrorHandler;
use Dskripchenko\LaravelApi\Interfaces\ApiInterface;
use Dskripchenko\LaravelApi\Traits\SwaggerApiTrait;
use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class BaseApi
 * @package Dskripchenko\LaravelApi\Components
 */
abstract class BaseApi implements ApiInterface
{
    use SwaggerApiTrait {
        SwaggerApiTrait::getSwaggerTemplates as public getSwaggerTemplatesTrait;
        SwaggerApiTrait::getSwaggerSecurityDefinitions as public getSwaggerSecurityDefinitionsTrait;
    }

    /**
     * @var array
     */
    protected static $preparedMethods = [];

    /**
     * @return array
     */
    public static function getMethods(): array
    {
        return [];
    }

    /**
     * [
     *      'User' => [
     *              'id' => [
     *                  'type' => 'integer',
     *                  'required' => true,
     *              ],
     *              'name' => [
     *                  'type' => 'string',
     *                  'required' => true,
     *              ],
     *      ],
     *      'Users' => [
     *          'list' => [
     *              'type' => 'array',
     *              'items' => '@User'
     *          ]
     *      ]
     * ];
     *
     * @return array
     */
    public static function getSwaggerTemplates(): array
    {
        return static::getSwaggerTemplatesTrait();
    }

    /**
     * @return array
     */
    public static function getSwaggerSecurityDefinitions(): array
    {
        return static::getSwaggerSecurityDefinitionsTrait();
    }

    /**
     * @return mixed
     */
    final public static function make(): Response
    {
        $controllerKey = ApiRequest::getApiControllerKey();
        $actionKey     = ApiRequest::getApiActionKey();
        $methods       = static::getPreparedMethods();

        $action = static::resolveAction($methods, $controllerKey, $actionKey);
        if (!$action) {
            throw new NotFoundHttpException('The requested method was not found!');
        }

        $requestMethod    = ApiRequest::method();
        $availableMethods = static::resolveAvailableMethods($methods, $controllerKey, $actionKey);
        if (!in_array($requestMethod, $availableMethods, true)) {
            $supportedMethods = implode(',', $availableMethods);
            $errorMessage = <<<RAW_STR
The '{$requestMethod}' method is not supported for this route. Supported method: '{$supportedMethods}'.
RAW_STR;
            throw new NotFoundHttpException($errorMessage);
        }

        return static::callAction($action);
    }

    /**
     * @param $action
     * @return Response
     */
    final public static function callAction($action): Response
    {
        try {
            $response = static::getDefaultEmptyResponse();
            if (static::beforeCallAction($action)) {
                $response = app()->call($action, ApiRequest::all(), 'index');
            }
            return static::afterCallAction($action, $response);
        } catch (\Exception $e) {
            return ApiErrorHandler::handle($e);
        }
    }

    /**
     * @param $action
     * @return bool
     */
    public static function beforeCallAction($action): bool
    {
        //for override
        return true;
    }

    /**
     * @param $action
     * @param $response Response
     * @return Response
     */
    public static function afterCallAction($action, Response $response): Response
    {
        //for override
        return $response;
    }

    /**
     * @return array
     */
    public static function getDefaultEmptyResponse(): array
    {
        //for override
        return [];
    }


    /**
     * @param $controllerKey
     * @param $actionKey
     *
     * @return array|string[]
     */
    private static function getAvailableMethod($controllerKey = null, $actionKey = null): array
    {
        return static::resolveAvailableMethods(
            static::getPreparedMethods(),
            $controllerKey ?: ApiRequest::getApiControllerKey(),
            $actionKey ?: ApiRequest::getApiActionKey()
        );
    }

    /**
     * @param string $controller
     * @param string $action
     *
     * @return string
     */
    public static function getActionMethod(string $controller, string $action): string
    {
        $methods = static::getAvailableMethod($controller, $action);

        return Arr::first($methods, null, 'post');
    }

    /**
     * @param array $methods
     * @param string|null $controllerKey
     * @param string|null $actionKey
     * @return array
     */
    private static function resolveAvailableMethods(array $methods, ?string $controllerKey, ?string $actionKey): array
    {
        $availableMethods = Arr::get(
            $methods,
            "controllers.{$controllerKey}.actions.{$actionKey}.method",
            ['post']
        );

        if (!$availableMethods) {
            return ['post'];
        }

        if (!is_array($availableMethods)) {
            return [$availableMethods];
        }

        return $availableMethods;
    }

    /**
     * @param array $methods
     * @param $controllerKey
     * @param $actionKey
     * @return bool|string
     */
    private static function resolveAction(array $methods, $controllerKey, $actionKey)
    {
        $controller = Arr::get(
            $methods,
            "controllers.{$controllerKey}.controller",
            false
        );

        if (!$controller) {
            return false;
        }

        $actions =  Arr::get($methods, "controllers.{$controllerKey}.actions", []);

        if (!isset($actions[$actionKey]) && !in_array($actionKey, $actions, true)) {
            return false;
        }

        if (isset($actions[$actionKey]) && $actions[$actionKey] === false) {
            return false;
        }

        if (isset($actions[$actionKey])) {
            if (is_string($actions[$actionKey])) {
                $actionKey = $actions[$actionKey];
            } elseif (
                is_array($actions[$actionKey])
                && isset($actions[$actionKey]['action'])
            ) {
                $actionKey = $actions[$actionKey]['action'];
            }
        }

        if (!method_exists($controller, $actionKey)) {
            return false;
        }

        return "{$controller}@{$actionKey}";
    }

    /**
     * @param $controllerKey
     * @param $actionKey
     * @return bool|string
     */
    private static function getPreparedAction($controllerKey, $actionKey)
    {
        return static::resolveAction(static::getPreparedMethods(), $controllerKey, $actionKey);
    }

    /**
     * @return array
     */
    public static function getMiddleware(): array
    {
        return static::getMiddlewareByControllerAndActionKey(
            ApiRequest::getApiControllerKey(),
            ApiRequest::getApiActionKey()
        );
    }

    /**
     * @param $controllerKey
     * @param $actionKey
     * @return array
     */
    private static function getMiddlewareByControllerAndActionKey(
        $controllerKey,
        $actionKey
    ): array {
        if (!static::getPreparedAction($controllerKey, $actionKey)) {
            return [];
        }

        $methods = static::getPreparedMethods();

        if (Arr::get($methods, "controllers.{$controllerKey}.exclude-all-middleware", false)) {
            return [];
        }

        if (Arr::get($methods, "controllers.{$controllerKey}.actions.{$actionKey}.exclude-all-middleware", false)) {
            return [];
        }

        $globalMiddleware     = Arr::get($methods, "middleware", []);
        $controllerMiddleware = Arr::get($methods, "controllers.{$controllerKey}.middleware", []);
        $actionMiddleware     = Arr::get($methods, "controllers.{$controllerKey}.actions.{$actionKey}.middleware", []);
        $middleware = array_merge($globalMiddleware, $controllerMiddleware, $actionMiddleware);

        $excludeControllerMiddleware = Arr::get($methods, "controllers.{$controllerKey}.exclude-middleware", []);
        $excludeActionMiddleware     = Arr::get($methods, "controllers.{$controllerKey}.actions.{$actionKey}.exclude-middleware", []);
        $excludeMiddleware = array_merge($excludeControllerMiddleware, $excludeActionMiddleware);
        $middleware        = array_diff($middleware, $excludeMiddleware);

        return array_unique($middleware);
    }

    /**
     * @return mixed
     */
    private static function getPreparedMethods(): array
    {
        if (!isset(static::$preparedMethods[static::class])) {
            static::$preparedMethods[static::class] = [];

            $parents = array_values(class_parents(static::class));
            $nonStaticParents = array_diff($parents, [self::class]);
            $nonStaticParents = array_reverse($nonStaticParents);

            foreach ($nonStaticParents as $className) {
                if (is_subclass_of($className, self::class)) {
                    static::$preparedMethods = array_merge_deep(static::$preparedMethods, $className::getNormalizedMethods());
                }
            }

            static::$preparedMethods[static::class] = array_merge_deep(static::$preparedMethods, static::getNormalizedMethods());
        }
        return static::$preparedMethods[static::class];
    }

    /**
     * @return array
     */
    private static function getNormalizedMethods(): array
    {
        $methods = static::getMethods();
        foreach (Arr::get($methods, 'controllers', []) as $controllerKey => $controller) {
            foreach (Arr::get($controller, 'actions', []) as $key => $value) {
                if (is_array($value) || $value === false) {
                    continue;
                }

                if (is_numeric($key)) {
                    if (is_null(Arr::get($methods, "controllers.{$controllerKey}.actions.{$value}"))) {
                        Arr::set($methods, "controllers.{$controllerKey}.actions.{$value}", []);
                        Arr::set($methods, "controllers.{$controllerKey}.actions.{$value}.action", $value);
                    }
                    Arr::pull($methods, "controllers.{$controllerKey}.actions.{$key}");
                    continue;
                }

                Arr::set($methods, "controllers.{$controllerKey}.actions.{$key}.action", $value);
            }
        }

        return $methods;
    }
}
