<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Operations\Doc;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlockFactory;

/**
 * Reads the docblock markup — for the OpenAPI generator and the linter alike.
 *
 * Both used to read the docblocks themselves, and they read them differently:
 * the generator took @input and @header from middleware groups too, the linter
 * from middleware classes only, and an empty tag crashed one and was a warning
 * for the other. What a tag means is decided here, once.
 */
final class OperationDocParser
{
    /** The tags of a middleware that pass on to the actions behind it. */
    private const MIDDLEWARE_TAGS = ['input', 'header'];

    private static ?DocBlockFactory $factory = null;

    /** @var array<string, DocLine[]> middleware class + tag → lines */
    private static array $middlewareLines = [];

    /**
     * @param  string|false|null  $comment  A doc comment, as reflection returns it.
     * @param  array  $middleware  The middleware the action runs through — classes or group names.
     */
    public static function parse(string|false|null $comment, array $middleware = []): OperationDoc
    {
        $docBlock = self::docBlock($comment);

        $lines = [];
        foreach ($docBlock->getTags() as $tag) {
            $lines[$tag->getName()][] = self::line($tag);
        }

        foreach (self::MIDDLEWARE_TAGS as $name) {
            $inherited = self::middlewareLines($name, $middleware);
            if ($inherited !== []) {
                $lines[$name] = array_merge($inherited, $lines[$name] ?? []);
            }
        }

        return new OperationDoc(
            self::sanitize($docBlock->getSummary()),
            self::sanitize($docBlock->getDescription()->render()),
            $lines,
        );
    }

    /** A docblock as phpDocumentor reads it; an empty one for no comment. */
    public static function docBlock(string|false|null $comment): DocBlock
    {
        self::$factory ??= DocBlockFactory::createInstance();

        return self::$factory->create($comment ?: ' ');
    }

    public static function flush(): void
    {
        self::$middlewareLines = [];
    }

    /**
     * Brings a docblock's text into a shape fit for a public spec.
     *
     * phpDocumentor returns the inline tags as they are, and they travel into
     * the OpenAPI raw: `{@see \App\Foo::bar()}` reads as rubbish and
     * `{@inheritDoc}` as an empty promise. We unfold them into text and leave
     * the links as URLs.
     */
    public static function sanitize(?string $text): string
    {
        $text = (string) $text;

        if ($text === '') {
            return '';
        }

        // {@inheritDoc} / {@inheritdoc} — there is nothing to unfold, they are removed.
        $text = preg_replace('/\{@inheritdoc}/i', '', $text);

        // {@link https://... description} → "description (https://...)", or just the URL.
        $text = preg_replace_callback(
            '/\{@link\s+(\S+)(?:\s+([^}]*))?}/i',
            static function (array $m): string {
                $url   = $m[1];
                $label = isset($m[2]) ? trim($m[2]) : '';

                return $label === '' ? $url : "{$label} ({$url})";
            },
            (string) $text
        );

        // {@see \Foo\Bar::baz()} → "Bar::baz()": a full namespace is not needed
        // in public documentation, and the short name keeps the reference's
        // meaning.
        $text = preg_replace_callback(
            '/\{@see\s+([^}\s]+)(?:\s+([^}]*))?}/i',
            static function (array $m): string {
                $label = isset($m[2]) ? trim($m[2]) : '';

                if ($label !== '') {
                    return $label;
                }

                $target = ltrim($m[1], '\\');
                $pos    = strrpos($target, '\\');

                return $pos === false ? $target : substr($target, $pos + 1);
            },
            (string) $text
        );

        return trim(preg_replace('/[ \t]+\n/', "\n", (string) $text));
    }

    private static function line(DocBlock\Tag $tag, ?string $middleware = null): DocLine
    {
        $description = method_exists($tag, 'getDescription') ? $tag->getDescription() : null;

        return new DocLine($tag->getName(), $description ? $description->render() : '', $middleware);
    }

    /** @return DocLine[] */
    private static function middlewareLines(string $tag, array $middleware): array
    {
        $lines = [];
        foreach ($middleware as $entry) {
            if (!is_string($entry)) {
                continue;
            }

            $classes = class_exists($entry)
                ? [$entry]
                : (array) Arr::get(Route::getMiddlewareGroups(), $entry, []);

            foreach ($classes as $class) {
                // A group may hold aliases (`throttle:api`), which have no
                // docblock to read.
                if (is_string($class) && class_exists($class)) {
                    array_push($lines, ...self::linesOfMiddleware($class, $tag));
                }
            }
        }

        return $lines;
    }

    /**
     * The lines of `run()`, or else `handle()`, of a middleware class.
     *
     * @param  class-string  $class
     * @return DocLine[]
     */
    private static function linesOfMiddleware(string $class, string $tag): array
    {
        $key = "{$class}\0{$tag}";
        if (isset(self::$middlewareLines[$key])) {
            return self::$middlewareLines[$key];
        }

        $reflection = new \ReflectionClass($class);
        $method = $reflection->hasMethod('run') ? 'run' : ($reflection->hasMethod('handle') ? 'handle' : null);

        $lines = [];
        if ($method !== null) {
            foreach (self::docBlock($reflection->getMethod($method)->getDocComment())->getTagsByName($tag) as $docTag) {
                $lines[] = self::line($docTag, $class);
            }
        }

        return self::$middlewareLines[$key] = $lines;
    }
}
