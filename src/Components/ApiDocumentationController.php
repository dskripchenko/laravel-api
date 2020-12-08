<?php


namespace Dskripchenko\LaravelApi\Components;


use Dskripchenko\LaravelApi\Facades\ApiModule;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class ApiDocumentationController extends Controller
{
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \ReflectionException
     */
    public function index()
    {
        $versionList = ApiModule::getApiVersionList();

        $folder = 'public/swagger';

        if (!Storage::exists($folder)) {
            Storage::makeDirectory($folder);
        }

        /**
         * @var BaseApi $api
         */
        foreach ($versionList as $version => $api) {
            $config = $api::getSwaggerApiConfig($version);
            $fileName = "{$version}.json";
            Storage::put("{$folder}/{$fileName}", json_encode($config));
            $filesData[] = [
                'url' => asset(Storage::url("{$folder}/{$fileName}") . '?r=' . uniqid('r_', true)),
                'name' => $version
            ];
        }

        return view(
            'api_module::api/documentation',
            [
                'filesJsonData' => json_encode($filesData)
            ]
        );
    }
}
