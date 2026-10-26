<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Operations\Doc;

use Dskripchenko\LaravelApi\Services\OpenApi\DocPatterns;

/**
 * One tag of a docblock: its name, its body, and where it came from.
 *
 * The body is kept as phpDocumentor renders it; {@see kind()} and {@see parts()}
 * read its shape — which of the forms of the markup it is, and the pieces of
 * it. That is syntax only: whether `@Model` names a defined template, or what
 * a `[method]` returns, is for the reader of the line to decide.
 */
final class DocLine
{
    /** A tag with no body. */
    public const EMPTY = 'empty';

    /** `[methodName]` — lines or a schema supplied by a controller method. */
    public const CALLABLE = 'callable';

    /** `@Model`, `@Model[]`, `@Model ?$variable Description`. */
    public const MODEL_REF = 'model-ref';

    /** Starts with `@`, and is not a model reference. */
    public const MALFORMED_MODEL_REF = 'malformed-model-ref';

    /** `{Template} Description`. */
    public const TEMPLATE = 'template';

    /** `type(format) ?$variable Description`. */
    public const FIELD = 'field';

    /** None of the forms. */
    public const MALFORMED = 'malformed';

    private ?array $parsed = null;

    /**
     * @param  string  $tag  The tag name, without `@`.
     * @param  string  $body  The rendered description; empty for a tag without one.
     * @param  class-string|null  $middleware  The middleware whose docblock it came from; null for the method's own.
     */
    public function __construct(
        public readonly string $tag,
        public readonly string $body,
        public readonly ?string $middleware = null,
    ) {
    }

    public function isOwn(): bool
    {
        return $this->middleware === null;
    }

    public function kind(): string
    {
        return $this->parse()[0];
    }

    /**
     * The named pieces of the line for its kind: `callable`; `model`, `isArray`,
     * `optional`, `variable`, `description`; `template`, `description`; `type`,
     * `format`, `optional`, `variable`, `description`. Empty for the others.
     *
     * @return array<string, string>
     */
    public function parts(): array
    {
        return $this->parse()[1];
    }

    /** The variable the line declares, if it declares one. */
    public function variable(): ?string
    {
        $variable = $this->parts()['variable'] ?? '';

        return $variable === '' ? null : $variable;
    }

    /** @return array{0: string, 1: array<string, string>} */
    private function parse(): array
    {
        if ($this->parsed !== null) {
            return $this->parsed;
        }

        $body = trim($this->body);

        if ($body === '') {
            return $this->parsed = [self::EMPTY, []];
        }

        if (preg_match(DocPatterns::inputsCallable(), $body, $matches)) {
            return $this->parsed = [self::CALLABLE, self::named($matches)];
        }

        if (str_starts_with($body, '@')) {
            return $this->parsed = preg_match(DocPatterns::modelRef(), $body, $matches)
                ? [self::MODEL_REF, self::named($matches)]
                : [self::MALFORMED_MODEL_REF, []];
        }

        if (str_starts_with($body, '{')) {
            return $this->parsed = [
                self::TEMPLATE,
                preg_match(DocPatterns::inputOutputTemplate(), $body, $matches) ? self::named($matches) : [],
            ];
        }

        return $this->parsed = preg_match(DocPatterns::inputOutput(), $body, $matches)
            ? [self::FIELD, self::named($matches)]
            : [self::MALFORMED, []];
    }

    /** @return array<string, string> */
    private static function named(array $matches): array
    {
        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }
}
