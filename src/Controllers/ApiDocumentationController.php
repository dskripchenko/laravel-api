<?php

namespace Dskripchenko\LaravelApi\Controllers;

use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApi\Facades\ApiModule;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\View\View;
use ReflectionException;

/**
 * Class ApiDocumentationController
 * @package Dskripchenko\LaravelApi\Controllers
 */
class ApiDocumentationController extends Controller
{
    /**
     * @return ViewFactory|View
     * @throws ReflectionException
     */
    public function index()
    {
        $versionList = ApiModule::getApiVersionList();

        $folder = 'public/swagger';

        if (!Storage::exists($folder)) {
            Storage::makeDirectory($folder);
        }
        $filesData = [];

        /**
         * @var BaseApi $api
         */
        foreach ($versionList as $version => $api) {
            $config   = $api::getSwaggerApiConfig($version);
            $fileName = "{$version}.json";
            Storage::put("{$folder}/{$fileName}", json_encode($config));
            $urlPath = Storage::url("{$folder}/{$fileName}");
            $urlHash = uniqid('', true);
            $url     = asset("{$urlPath}?r={$urlHash}");
            $filesData[] = [
                'url' => $url,
                'name' => $config['info']['title'] ?: $version
            ];
        }

        return view('api_module::api/documentation', [
            'filesJsonData' => json_encode($filesData)
        ]);
    }
}
