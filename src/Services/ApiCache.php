<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Services;

use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApi\Components\BaseModule;

/**
 * The API map frozen into a file — what `php artisan api:cache` writes.
 *
 * Every request used to rebuild it: the prepared methods of each version, and
 * the route definitions of all of them, the middleware cascade of every action
 * included. For a large API that is milliseconds per request spent on an
 * answer that does not change between deployments.
 *
 * Like `route:cache` and `config:cache`, it freezes what getMethods() returned
 * when the command ran: an API whose map depends on the request, the database
 * or anything else that changes at runtime must not be cached.
 */
final class ApiCache
{
    /** Bumped when the shape of the file changes; a file of another shape is ignored. */
    private const FORMAT = 1;

    /** @var array|false|null The file's contents; false when there is none; null before it was looked for. */
    private static array|false|null $loaded = null;

    public static function path(): string
    {
        return app()->bootstrapPath('cache/laravel-api.php');
    }

    public static function exists(): bool
    {
        return self::load() !== false;
    }

    /**
     * The cached prepared methods of an API class, or null when it is not cached.
     *
     * @param  class-string  $apiClass
     */
    public static function preparedMethods(string $apiClass): ?array
    {
        $cache = self::load();

        return $cache === false ? null : ($cache['prepared'][$apiClass] ?? null);
    }

    /**
     * The cached route definitions of a module, or null when they are not cached
     * — or were cached for another module.
     *
     * @param  class-string  $moduleClass
     */
    public static function routeDefinitions(string $moduleClass): ?array
    {
        $cache = self::load();

        if ($cache === false || ($cache['module'] ?? null) !== $moduleClass) {
            return null;
        }

        return $cache['routes'];
    }

    /**
     * What the cache of a module holds: the prepared methods of every version
     * class and the module's route definitions.
     *
     * @throws \UnexpectedValueException When the map holds something a PHP file cannot carry.
     */
    public static function build(BaseModule $module): array
    {
        $prepared = [];
        foreach ($module->getApiVersionList() as $apiClass) {
            if (is_string($apiClass) && is_subclass_of($apiClass, BaseApi::class)) {
                $prepared[$apiClass] = $apiClass::getPreparedMethods();
            }
        }

        $data = [
            'format' => self::FORMAT,
            'module' => get_class($module),
            'prepared' => $prepared,
            'routes' => $module->getRouteDefinitions(),
        ];

        self::assertExportable($data, '');

        return $data;
    }

    /** Writes the cache, replacing the file in one step. */
    public static function write(array $data): void
    {
        $path = self::path();
        $temporary = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';

        file_put_contents($temporary, '<?php return ' . var_export($data, true) . ';' . PHP_EOL);
        rename($temporary, $path);

        self::flush();
    }

    /** Removes the cache. Whether there was one to remove. */
    public static function clear(): bool
    {
        self::flush();
        $path = self::path();

        return is_file($path) && unlink($path);
    }

    /** Forgets what was read, so the file is looked for again. */
    public static function flush(): void
    {
        self::$loaded = null;
    }

    private static function load(): array|false
    {
        if (self::$loaded !== null) {
            return self::$loaded;
        }

        $path = self::path();
        $data = is_file($path) ? require $path : false;

        return self::$loaded = is_array($data) && ($data['format'] ?? null) === self::FORMAT ? $data : false;
    }

    /** var_export() writes an object as a call that brings back nothing useful. */
    private static function assertExportable(mixed $value, string $path): void
    {
        if (is_object($value) || is_resource($value)) {
            throw new \UnexpectedValueException(
                "The API map holds a " . get_debug_type($value) . " at `{$path}`, which a cache file cannot carry."
            );
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                self::assertExportable($item, $path === '' ? (string) $key : "{$path}.{$key}");
            }
        }
    }
}
