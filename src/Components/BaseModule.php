<?php

namespace Dskripchenko\LaravelApi\Components;

use Dskripchenko\LaravelApi\Facades\ApiModule;
use Dskripchenko\LaravelApi\Facades\ApiRequest;
use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class BaseModule
 * @package Dskripchenko\LaravelApi\Components
 */
class BaseModule
{
    /**
     * @var array
     */
    protected $resolvedApis = [];


    /**
     * @param string|null $version
     *
     * @return BaseApi|null
     */
    public function getApi(string $version = null): ?string
    {
        if (!$version) {
            $version = ApiRequest::getApiVersion();
        }

        if (!$version) {
            $request = ApiRequest::getInstance();
            $version = $request ? (string) $request->server('TESTING_API_VERSION') : null;
        }

        if (!$version) {
            return null;
        }

        if (!isset($this->resolvedApis[$version])) {
            $this->resolvedApis[$version] = Arr::get(ApiModule::getApiVersionList(), $version, false);
        }

        $api = $this->resolvedApis[$version];
        if (!is_subclass_of($api, BaseApi::class)) {
            return null;
        }

        return $api;
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function makeApi()
    {
        /**
         * @var BaseApi $api
         */
        $api = $this->getApi();
        if (!$api) {
            throw new NotFoundHttpException('The requested version is not active!');
        }
        return $api::make();
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getApiMiddleware(): array
    {
        /**
         * @var BaseApi $api
         */
        $api = $this->getApi();
        if (!$api) {
            return [];
        }
        return $api::getMiddleware();
    }

    /**
     * @return string
     */
    public function getApiPrefix(): string
    {
        return config('laravel-api.prefix', 'api');
    }

    /**
     * @return array
     */
    public function getAvailableApiMethods(): array
    {
        return config('laravel-api.available_methods', ['get', 'post', 'put', 'patch', 'delete']);
    }

    /**
     * @return string
     */
    public function getApiUriPattern(): string
    {
        return config('laravel-api.uri_pattern', '{version}/{controller}/{action}');
    }

    /**
     * @return array
     */
    public function getApiVersionList(): array
    {
        return [
            //api version list
        ];
    }

    /**
     * @return array
     */
    public function getDocMiddleware(): array
    {
        return [];
    }

    /**
     * Returns route definitions for all versions and actions.
     *
     * Each entry: ['version' => ..., 'controller' => ..., 'action' => ..., 'methods' => [...], 'name' => ..., 'uri' => ...]
     *
     * @return array
     */
    public function getRouteDefinitions(): array
    {
        $definitions = [];
        $prefix = $this->getApiPrefix();
        $uriPattern = $this->getApiUriPattern();

        foreach ($this->getApiVersionList() as $version => $apiClass) {
            if (!is_subclass_of($apiClass, BaseApi::class)) {
                continue;
            }

            $methods = $apiClass::getPreparedMethods();
            $controllers = Arr::get($methods, 'controllers', []);

            foreach ($controllers as $controllerKey => $controllerConfig) {
                $actions = Arr::get($controllerConfig, 'actions', []);

                foreach ($actions as $actionKey => $actionConfig) {
                    if ($actionConfig === false) {
                        continue;
                    }

                    $httpMethods = Arr::get($actionConfig, 'method', ['post']);
                    if (!$httpMethods) {
                        $httpMethods = ['post'];
                    }
                    if (!is_array($httpMethods)) {
                        $httpMethods = [$httpMethods];
                    }

                    $customName = is_array($actionConfig) ? Arr::get($actionConfig, 'name') : null;
                    $nameSuffix = $customName ?: "{$controllerKey}.{$actionKey}";
                    $routeName = "api.{$version}.{$nameSuffix}";

                    $uri = str_replace(
                        ['{version}', '{controller}', '{action}'],
                        [$version, $controllerKey, $actionKey],
                        $uriPattern
                    );

                    $definitions[] = [
                        'version' => $version,
                        'controller' => $controllerKey,
                        'action' => $actionKey,
                        'methods' => $httpMethods,
                        'name' => $routeName,
                        'uri' => $uri,
                    ];
                }
            }
        }

        return $definitions;
    }
}
