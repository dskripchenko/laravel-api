<?php

namespace Dskripchenko\LaravelApi\Requests;

use Dskripchenko\LaravelApi\Exceptions\ApiException;
use Dskripchenko\LaravelApi\Facades\ApiModule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

/**
 * Class BaseApiRequest
 * @package Dskripchenko\LaravelApi\Requests
 */
class BaseApiRequest extends FormRequest
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
    public function getApiVersion(): ?string
    {
        return $this->apiVersion;
    }

    /**
     * @return string
     */
    public function getApiMethod(): string
    {
        return $this->apiMethod;
    }


    /**
     * @return string
     */
    public function getApiControllerKey(): ?string
    {
        return $this->apiController;
    }

    /**
     * @return string
     */
    public function getApiActionKey(): ?string
    {
        return $this->apiAction;
    }

    /**
     * @return BaseApiRequest
     * @throws \Exception
     */
    public static function getInstance(): ?BaseApiRequest
    {
        if (is_null(static::$_instance)) {
            static::$_instance = static::capture();
            static::$_instance->apiPrefix     = ApiModule::getApiPrefix();
            static::$_instance->apiUriPattern = ApiModule::getApiUriPattern();
            static::$_instance->validateApiUriPattern();
            static::$_instance->prepareApi();
        }

        return static::$_instance;
    }


    /**
     * @throws \Exception
     */
    protected function validateApiUriPattern(): void
    {
        $patternParts = explode('/', $this->apiUriPattern);
        if (!in_array('{version}', $patternParts, true)) {
            throw new ApiException('api_make_error', 'Некорректный паттерн api, отсутствует версия {version}');
        }
        if (!in_array('{controller}', $patternParts, true)) {
            throw new ApiException('api_make_error', 'Некорректный паттерн api, отсутствует контроллер {controller}');
        }
        if (!in_array('{action}', $patternParts, true)) {
            throw new ApiException('api_make_error', 'Некорректный паттерн api, отсутствует экшен {action}');
        }
    }


    protected function prepareApi(): void
    {
        $path = $this->getPathInfo();
        if (strpos($path, $this->apiPrefix) === false) {
            return;
        }

        $method      = str_replace("/{$this->apiPrefix}/", '', $path);
        $methodParts = explode('/', $method);

        $patternParts = explode('/', $this->apiUriPattern);

        if (count($patternParts) != count($methodParts)) {
            return;
        }

        $keys   = array_values($patternParts);
        $values = array_slice(array_values($methodParts), 0, count($keys));
        $data   = array_combine($keys, $values);

        $this->apiVersion    = Arr::get($data, '{version}', $this->server('TESTING_API_VERSION'));
        $this->apiController = Arr::get($data, '{controller}', $this->server('TESTING_API_CONTROLLER'));
        $this->apiAction     = Arr::get($data, '{action}', $this->server('TESTING_API_ACTION'));
    }

    /**
     * @return array
     */
    public function rules()
    {
        return [];
    }

    /**
     * @return string
     */
    public function method(): string
    {
        return strtolower(parent::method());
    }
}
