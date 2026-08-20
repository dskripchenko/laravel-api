<?php

namespace Dskripchenko\LaravelApi\Controllers;

use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApi\Facades\ApiModule;
use Dskripchenko\LaravelApi\Services\OpenApi\DocLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
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
     * Render the API reference UI.
     *
     * Specs are served through the {@see self::source()} route rather than as
     * static files, so the viewer works on any Laravel install without relying
     * on `storage:link` or the local disk layout.
     *
     * @return ViewFactory|View
     * @throws ReflectionException
     */
    public function index()
    {
        $versionList = $this->listableVersions();

        // Generate (and cache) every version first so the spec files exist
        // before we build the URLs that point at them.
        $configs = [];
        foreach ($versionList as $version => $api) {
            $configs[$version] = $this->buildConfig($version, $api);
        }

        $filesData = [];
        foreach ($configs as $version => $config) {
            $filesData[] = [
                'url'  => route('api-doc-source', ['version' => $version]) . '?r=' . $this->cacheStamp($version),
                'name' => ($config['info']['title'] ?? '') ?: $version,
                // Without this Scalar derives the slug from the title, and the
                // title is the first line of a docblock: reword it and every
                // link into the page — bookmarks, tickets, the IDE's — points
                // at nothing, silently. The version's own name is what the URL
                // already carries, so it costs a reader nothing to recognise.
                'slug' => DocLink::documentSlug($version),
            ];
        }

        return view('api_module::api/documentation', [
            'filesData' => $filesData,
            'documentationScript' => (string) config(
                'laravel-api.documentation_script',
                'https://cdn.jsdelivr.net/npm/@scalar/api-reference'
            ),
        ]);
    }

    /**
     * Versions shown in the reference UI.
     *
     * Not every API version is meant for outside readers: admin panels and
     * other internal surfaces are part of the same module, and listing them
     * publishes their whole endpoint map. `laravel-api.hidden_versions` keeps
     * them out of the index; their specs stay reachable by direct URL for
     * whoever needs them.
     *
     * @return array<string, class-string<BaseApi>>
     */
    protected function listableVersions(): array
    {
        $hidden = (array) config('laravel-api.hidden_versions', []);

        return array_diff_key(ApiModule::getApiVersionList(), array_flip($hidden));
    }

    /**
     * Return the OpenAPI spec for a single version as JSON.
     *
     * @throws ReflectionException
     */
    public function source(string $version): JsonResponse
    {
        $versionList = ApiModule::getApiVersionList();

        if (!isset($versionList[$version])) {
            abort(404);
        }

        return response()->json($this->buildConfig($version, $versionList[$version]));
    }

    /**
     * Build the OpenAPI config for a version, caching it to disk. The cache is
     * bypassed in debug mode so docs stay fresh during development.
     *
     * @param class-string<BaseApi> $api
     * @throws ReflectionException
     */
    protected function buildConfig(string $version, string $api): array
    {
        $filePath = $this->specPath($version);

        if (!Storage::exists($filePath) || app()->hasDebugModeEnabled()) {
            $config = $api::getOpenApiConfig($version);
            Storage::put($filePath, json_encode($config));

            return $config;
        }

        return json_decode(Storage::get($filePath), true);
    }

    /**
     * Cache-busting stamp for a version's spec URL.
     */
    protected function cacheStamp(string $version): int
    {
        $filePath = $this->specPath($version);

        return Storage::exists($filePath) ? (filemtime(Storage::path($filePath)) ?: time()) : time();
    }

    /**
     * Storage path of a version's cached spec file, ensuring the folder exists.
     */
    protected function specPath(string $version): string
    {
        $folder = config('laravel-api.openapi_path', 'public/openapi');

        if (!Storage::exists($folder)) {
            Storage::makeDirectory($folder);
        }

        return "{$folder}/{$version}.json";
    }
}
