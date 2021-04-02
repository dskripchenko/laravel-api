<?php

namespace Dskripchenko\LaravelApi\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Class BaseServiceProvider
 * @package Dskripchenko\LaravelApi\Providers
 */
class BaseServiceProvider extends ServiceProvider
{
    /**
     * Merge the given configuration with the existing configuration.
     *
     * @param string $path
     * @param string $key
     * @param bool $prefer
     * @return void
     */
    protected function mergeConfigFrom($path, $key, $prefer = false): void
    {
        if (!$this->app->configurationIsCached()) {
            if ($prefer) {
                $result = array_merge_deep($this->app['config']->get($key, []), require $path);
            }
            else {
                $result = array_merge_deep(require $path, $this->app['config']->get($key, []));
            }

            $this->app['config']->set($key, $result);
        }
    }
}
