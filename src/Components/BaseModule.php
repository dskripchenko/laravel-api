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
     * @var BaseApi
     */
    protected $api;


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

        if (!$this->api) {
            $this->api = Arr::get(ApiModule::getApiVersionList(), $version, false);
        }
        if (!is_subclass_of($this->api, BaseApi::class)) {
            return null;
        }

        return $this->api;
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
        return 'api';
    }

    /**
     * @return array
     */
    public function getAvailableApiMethods(): array
    {
        return ['get', 'post', 'put', 'patch', 'delete'];
    }

    /**
     * @return string
     */
    public function getApiUriPattern(): string
    {
        return '{version}/{controller}/{action}';
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
}
