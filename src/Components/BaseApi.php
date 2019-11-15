<?php


namespace Dskripchenko\LaravelApi\Components;

use Dskripchenko\LaravelApi\Facades\ApiRequest;
use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class BaseApi
{
    use SwaggerApiTrait{
        getSwaggerTemplates as public getSwaggerTemplatesTrait;
    }

    /**
     * @var array
     */
    protected static $preparedMethods = [];

    /**
     * @var string|null
     */
    protected static $action = null;

    /**
     * @return array
     *
     *
     * 'controllers' => [
     *   'user' => [
     *       'controller' => \App\Api\Versions\v1_0\Controllers\UserController::class,
     *       'actions' => [
     *          'register' => [
     *              'exclude-all-middleware' => true, //TODO исключить все middleware на уровне экшена
     *          ],
     *          'login' => [],
     *          'logout' => false,
     *          'limited-access' => [
     *              'action' => 'limitedAccess',
     *              'middleware' => [
     *                  VerifyApiToken::class
     *              ]
     *          ],
     *          'get-sign' => 'getSign',
     *          'checkSign' => [
     *              'middleware' => [ //TODO middleware на уровне экшена
     *                  VerifyApiSign::class
     *              ],
     *              'exclude-middleware' => [], //TODO исключить middleware для контроллера
     *          ],
     *       ],
     *       'exclude-all-middleware' => true, //TODO исключить все middleware для контроллера
     *       'middleware' => [], //TODO сквозные middleware на уровне контроллера
     *       'exclude-middleware' => [], //TODO исключить middleware для контроллера
     *   ]
     * ],
     * 'middleware' => [] //TODO сквозные middleware на уровне всего апи
     */
    abstract protected static function getMethods();

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
    public static function getSwaggerTemplates()
    {
        return static::getSwaggerTemplatesTrait();
    }

    /**
     * @return mixed
     */
    final public static function make(){
        $action = static::getAction();
        if(!$action){
            throw new NotFoundHttpException('The requested method was not found!');
        }
        return static::callAction($action);
    }

    /**
     * @param $action
     * @return array|mixed
     */
    final public static function callAction($action){
        try {
            return app()->call($action);
        }
        catch (ApiException $e){
            return ApiResponseHelper::sayError([
                'errorKey' => $e->getErrorKey(),
                'message' => $e->getMessage(),
            ]);
        }
        catch (\Exception $e){
            return ApiResponseHelper::sayError([
                'errorKey' => $e->getCode(),
                'message' => $e->getMessage(),
            ]);
        }
    }


    /**
     * @return string|null
     */
    private static function getAction(){
        if (static::$action === null){
            static::$action = static::getPreparedAction(ApiRequest::getApiControllerKey(), ApiRequest::getApiActionKey());
        }
        return static::$action;
    }


    /**
     * @param $controllerKey
     * @param $actionKey
     * @return bool|string
     */
    private static function getPreparedAction($controllerKey, $actionKey){
        $methods = static::getPreparedMethods();
        $controller = Arr::get($methods, "controllers.{$controllerKey}.controller", false);
        if(!$controller){
            return false;
        }
        $actions =  Arr::get($methods, "controllers.{$controllerKey}.actions", []);
        if(!isset($actions[$actionKey]) && !in_array($actionKey, $actions)){
            return false;
        }
        if(isset($actions[$actionKey]) && $actions[$actionKey] === false){
            return false;
        }

        if(isset($actions[$actionKey])){
            if(is_string($actions[$actionKey])){
                $actionKey = $actions[$actionKey];
            }
            elseif (is_array($actions[$actionKey]) && isset($actions[$actionKey]['action'])){
                $actionKey = $actions[$actionKey]['action'];
            }
        }

        if(!method_exists($controller, $actionKey)){
            return false;
        }

        return "{$controller}@{$actionKey}";
    }

    /**
     * @return array
     */
    public static function getMiddleware(){
        return static::getMiddlewareByControllerAndActionKey(ApiRequest::getApiControllerKey(), ApiRequest::getApiActionKey());
    }

    /**
     * @param $controllerKey
     * @param $actionKey
     * @return array
     */
    private static function getMiddlewareByControllerAndActionKey($controllerKey, $actionKey){
        if (!static::getPreparedAction($controllerKey, $actionKey)){
            return [];
        }

        $methods = static::getPreparedMethods();

        if(Arr::get($methods, "controllers.{$controllerKey}.exclude-all-middleware", false)){
            return [];
        }

        if(Arr::get($methods, "controllers.{$controllerKey}.actions.{$actionKey}.exclude-all-middleware", false)){
            return [];
        }

        $globalMiddleware = Arr::get($methods, "middleware", []);
        $controllerMiddleware = Arr::get($methods, "controllers.{$controllerKey}.middleware", []);
        $actionMiddleware = Arr::get($methods, "controllers.{$controllerKey}.actions.{$actionKey}.middleware", []);
        $middleware = array_merge($globalMiddleware, $controllerMiddleware, $actionMiddleware);

        $excludeControllerMiddleware = Arr::get($methods, "controllers.{$controllerKey}.exclude-middleware", []);
        $excludeActionMiddleware = Arr::get($methods, "controllers.{$controllerKey}.actions.{$actionKey}.exclude-middleware", []);
        $excludeMiddleware = array_merge($excludeControllerMiddleware, $excludeActionMiddleware);
        $middleware = array_diff($middleware, $excludeMiddleware);

        return array_unique($middleware);
    }

    /**
     * @return mixed
     */
    private static function getPreparedMethods(){
        if (!isset(static::$preparedMethods[static::class])){
            static::$preparedMethods[static::class] = [];

            $parents = array_values(class_parents(static::class));
            $nonStaticParents = array_diff($parents, [self::class]);
            $nonStaticParents = array_reverse($nonStaticParents);

            foreach ($nonStaticParents as $className){
                if (method_exists($className, 'getMethods')){
                    static::$preparedMethods = ArrayMergeHelper::merge(static::$preparedMethods, $className::getMethods());
                }
            }

            static::$preparedMethods[static::class] = ArrayMergeHelper::merge(static::$preparedMethods, static::getMethods());
        }
        return static::$preparedMethods[static::class];
    }
}