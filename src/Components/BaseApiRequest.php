<?php


namespace Dskripchenko\LaravelApi\Components;



use Dskripchenko\LaravelApi\Facades\ApiModule;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class BaseApiRequest extends Request
{
    /**
     * @var static|null
     */
    protected static $_instance = null;

    /**
     * @var string
     */
    protected $apiPrefix;

    /**
     * @var static
     */
    protected $apiUriPattern;

    /**
     * @var string|null
     */
    protected $apiVersion;

    /**
     * @var string|null
     */
    protected $apiController;

    /**
     * @var string|null
     */
    protected $apiAction;

    /**
     * @return string
     */
    public function getApiVersion()
    {
        return $this->apiVersion;
    }

    /**
     * @return string
     */
    public function getApiMethod()
    {
        return $this->apiMethod;
    }


    /**
     * @return string
     */
    public function getApiControllerKey()
    {
        return $this->apiController;
    }

    /**
     * @return string
     */
    public function getApiActionKey()
    {
        return $this->apiAction;
    }

    /**
     * @return BaseApiRequest
     * @throws \Exception
     */
    public static function getInstance(){
        if(is_null(static::$_instance)){
            static::$_instance = static::capture();
            static::$_instance->apiPrefix = ApiModule::getApiPrefix();
            static::$_instance->apiUriPattern = ApiModule::getApiUriPattern();
            static::$_instance->validateApiUriPattern();
            static::$_instance->prepareApi();
        }

        return static::$_instance;
    }


    /**
     * @throws \Exception
     */
    protected function validateApiUriPattern(){
        $patternParts = explode('/', $this->apiUriPattern);
        if(!in_array('{version}', $patternParts)){
            throw new \Exception('Некорректный паттерн api, отсутствует версия {version}');
        }
        if(!in_array('{controller}', $patternParts)){
            throw new \Exception('Некорректный паттерн api, отсутствует контроллер {controller}');
        }
        if(!in_array('{action}', $patternParts)){
            throw new \Exception('Некорректный паттерн api, отсутствует экшен {action}');
        }
    }


    protected function prepareApi()
    {
        $path = $this->getPathInfo();
        if(strpos($path, $this->apiPrefix) === false){
            return;
        }

        $method = str_replace("/{$this->apiPrefix}/",'',$path);
        $methodParts = explode('/',$method);

        $patternParts = explode('/', $this->apiUriPattern);

        if(count($patternParts) != count($methodParts)){
            return;
        }

        $keys = array_values($patternParts);
        $values = array_slice(array_values($methodParts), 0, count($keys));
        $data = array_combine($keys, $values);

        $this->apiVersion = Arr::get($data,'{version}');
        $this->apiController = Arr::get($data,'{controller}');
        $this->apiAction = Arr::get($data,'{action}');
    }

}