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

        $folder = config('laravel-api.openapi_path', 'public/openapi');

        if (!Storage::exists($folder)) {
            Storage::makeDirectory($folder);
        }
        $filesData = [];

        /**
         * @var BaseApi $api
         */
        foreach ($versionList as $version => $api) {
            $fileName = "{$version}.json";
            $filePath = "{$folder}/{$fileName}";

            if (!Storage::exists($filePath) || app()->hasDebugModeEnabled()) {
                $config = $api::getOpenApiConfig($version);
                Storage::put($filePath, json_encode($config));
            } else {
                $config = json_decode(Storage::get($filePath), true);
            }

            $urlPath = Storage::url($filePath);
            $urlHash = filemtime(Storage::path($filePath)) ?: time();
            $url     = asset("{$urlPath}?r={$urlHash}");
            $filesData[] = [
                'url' => $url,
                'name' => ($config['info']['title'] ?? '') ?: $version
            ];
        }

        return view('api_module::api/documentation', [
            'filesJsonData' => json_encode($filesData)
        ]);
    }
}
