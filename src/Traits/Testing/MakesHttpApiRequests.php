<?php

namespace Dskripchenko\LaravelApi\Traits\Testing;


use Dskripchenko\LaravelApi\Facades\ApiModule;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use RuntimeException;

trait MakesHttpApiRequests
{
    use MakesHttpRequests;

    /**
     * The Illuminate application instance.
     *
     * @var Application
     */
    protected $app;

    /**
     * @param string $version
     * @param string $controller
     * @param string $action
     * @param array $data
     * @param array $headers
     *
     * @return TestResponse
     * @throws BindingResolutionException
     */
    public function api(string $version, string $controller, string $action, array $data = [], array $headers = []): TestResponse
    {
        $this->serverVariables['TESTING_API_VERSION'] = $version;
        $this->serverVariables['TESTING_API_CONTROLLER'] = $controller;
        $this->serverVariables['TESTING_API_ACTION'] = $action;

        $server = $this->transformHeadersToServerVars($headers);
        $cookies = $this->prepareCookiesForRequest();

        $prefix = ApiModule::getApiPrefix();
        $uri = implode('/', [$prefix, $version, $controller, $action]);

        $api = ApiModule::getApi($version);
        if (!$api) {
            throw new RuntimeException('The requested version is not active!');
        }

        $method = $api::getActionMethod($controller, $action);
        return $this->call(strtoupper($method), $uri, $data, $cookies, [], $server);
    }

    /**
     * @param $method
     * @param $uri
     * @param $parameters
     * @param $cookies
     * @param $files
     * @param $server
     * @param $content
     *
     * @return TestResponse
     * @throws BindingResolutionException
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $kernel = $this->app->make(HttpKernel::class);

        $files = array_merge($files, $this->extractFilesFromDataArray($parameters));

        $symfonyRequest = SymfonyRequest::create(
            $this->prepareUrlForRequest($uri),
            $method,
            $parameters,
            $cookies,
            $files,
            array_replace($this->serverVariables, $server),
            $content
        );

        $response = $kernel->handle(
            $request = $this->createTestRequest($symfonyRequest)
        );

        $kernel->terminate($request, $response);

        if ($this->followRedirects) {
            $response = $this->followRedirects($response);
        }

        return $this->createTestResponse($response, $request);
    }
}