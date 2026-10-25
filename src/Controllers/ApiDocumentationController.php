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
                // The version's id on the page and the first segment of its
                // anchors. Derived from the title, it would change whenever the
                // docblock is reworded, and every link into the page —
                // bookmarks, tickets, the IDE's — would point at nothing. The
                // version's own name is what the URL already carries.
                'slug' => DocLink::documentSlug($version),
            ];
        }

        return view('api_module::api/documentation', [
            'filesData' => $filesData,
            'documentationScript' => static::documentationScript(),
            'documentationOptions' => $this->documentationOptions($filesData),
        ]);
    }

    /** The pinned script on jsdelivr; config/laravel-api.php repeats it (a test keeps them equal). */
    public const DEFAULT_SCRIPT = 'https://cdn.jsdelivr.net/npm/@dskripchenko/openapi@1.0.0/dist/standalone.js';

    /** The URL of the reference's script (`laravel-api.documentation.script`). */
    public static function documentationScript(): string
    {
        return (string) (config('laravel-api.documentation.script') ?: static::DEFAULT_SCRIPT);
    }

    /**
     * The options `DskOpenApi.createApiReference()` gets: the versions as
     * sources (their slugs are DocLink's document slugs, so its anchors land)
     * and the page settings from `laravel-api.documentation`.
     *
     * @param array<int, array{url: string, name: string, slug: string}> $filesData
     * @return array<string, mixed>
     */
    protected function documentationOptions(array $filesData): array
    {
        $config = (array) config('laravel-api.documentation', []);
        $persist = $config['persist_credentials'] ?? false;
        if (is_string($persist) && !in_array($persist, ['session', 'local'], true)) {
            $persist = filter_var($persist, FILTER_VALIDATE_BOOLEAN);
        }

        $options = [
            'sources' => array_map(static fn (array $file): array => [
                'url'   => $file['url'],
                'title' => $file['name'],
                'slug'  => $file['slug'],
            ], $filesData),
            'locale' => (string) ($config['locale'] ?? 'auto'),
            'theme' => (string) ($config['theme'] ?? 'system'),
            'persistTheme' => true,
            'tryIt' => filter_var($config['try_it'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'persistCredentials' => $persist,
            'defaultSnippet' => (string) ($config['default_snippet'] ?? 'curl'),
        ];
        if (!empty($config['title'])) {
            $options['title'] = (string) $config['title'];
        }
        if (!empty($config['proxy'])) {
            $options['proxy'] = (string) $config['proxy'];
        }
        if (!empty($config['oauth_redirect_url'])) {
            $options['oauthRedirectUrl'] = (string) $config['oauth_redirect_url'];
        }
        if (!empty($config['snippet_targets']) && is_array($config['snippet_targets'])) {
            $options['snippetTargets'] = array_values($config['snippet_targets']);
        }

        return $options;
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
