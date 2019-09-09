<?php


namespace Dskripchenko\LaravelApi\Components;


use Dskripchenko\LaravelApi\Facades\ApiModule;
use Dskripchenko\LaravelApi\Facades\ApiRequest;
use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BaseModule
{
    /**
     * @var BaseApi
     */
    protected $api;


    /**
     * @return BaseApi|null
     */
    protected function getApi(){
        if(!$this->api){
            $this->api = Arr::get(ApiModule::getApiVersionList(), ApiRequest::getApiVersion(), false);
        }
        if(!is_subclass_of($this->api, BaseApi::class)){
            return null;
        }

        return $this->api;
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function makeApi(){
        /**
         * @var BaseApi $api
         */
        $api = $this->getApi();
        if (!$api){
            throw new NotFoundHttpException('The requested version is not active!');
        }
        return $api::make();
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getApiMiddleware(){
        /**
         * @var BaseApi $api
         */
        $api = $this->getApi();
        if(!$api){
            return [];
        }
        return $api::getMiddleware();
    }

    /**
     * @return string
     */
    public function getApiPrefix(){
        return 'api';
    }

    /**
     * @return array
     */
    public function getAvailableApiMethods(){
        return ['post'];
    }

    /**
     * @return string
     */
    public function getApiUriPattern(){
        return '{version}/{controller}/{action}';
    }

    /**
     * @return string
     */
    public function getControllerNamespace(){
        return 'App\Api\Versions';
    }

    /**
     * @return array
     */
    public static function getApiVersionList(){
        return [
            //api version list
        ];
    }

}