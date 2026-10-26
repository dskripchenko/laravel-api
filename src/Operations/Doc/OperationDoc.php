<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Operations\Doc;

use Dskripchenko\LaravelApi\Services\OpenApi\DocPatterns;

/**
 * What the docblock of an action says, read once.
 *
 * The lines of @input and @header include those of the middleware the action
 * runs through, ahead of the method's own — a middleware that needs a token
 * documents the header once, and every action behind it carries it. The other
 * tags are the method's alone.
 */
final class OperationDoc
{
    /**
     * @param  string  $summary  The first paragraph, ready for a public spec.
     * @param  string  $description  The rest of the text, ready for a public spec.
     * @param  array<string, DocLine[]>  $lines  Tag name → lines, in order.
     */
    public function __construct(
        public readonly string $summary,
        public readonly string $description,
        private readonly array $lines,
    ) {
    }

    /** @return DocLine[] */
    public function lines(string $tag): array
    {
        return $this->lines[$tag] ?? [];
    }

    /** @return DocLine[] */
    public function inputs(): array
    {
        return $this->lines('input');
    }

    /** @return DocLine[] */
    public function outputs(): array
    {
        return $this->lines('output');
    }

    /** @return DocLine[] */
    public function headers(): array
    {
        return $this->lines('header');
    }

    /** @return DocLine[] */
    public function responses(): array
    {
        return $this->lines('response');
    }

    public function isDeprecated(): bool
    {
        return $this->lines('deprecated') !== [];
    }

    /**
     * The names @security lists, in order.
     *
     * @return string[]
     */
    public function securitySchemes(): array
    {
        $names = [];
        foreach ($this->lines('security') as $line) {
            $name = trim($line->body);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * `@default` and `@example`, by variable: `['page' => ['default' => 1, 'example' => 3]]`.
     * The value is cast as a literal — numbers, `true`, `false`, `null`.
     *
     * @return array<string, array{default?: mixed, example?: mixed}>
     */
    public function defaultsAndExamples(): array
    {
        $result = [];
        foreach (['default', 'example'] as $tag) {
            foreach ($this->lines($tag) as $line) {
                if (preg_match(DocPatterns::defaultExample(), $line->body, $matches)) {
                    $result[$matches['variable']][$tag] = self::castValue($matches['value']);
                }
            }
        }

        return $result;
    }

    public static function castValue(string $value): mixed
    {
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return match ($value) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => $value,
        };
    }
}
