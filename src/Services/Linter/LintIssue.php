<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Services\Linter;

/**
 * One finding: what is wrong, where, and how badly.
 *
 * `where` is deliberately a human address — `v1 · item.list` — rather than a
 * file and a line. The markup is spread across a controller's docblock, the
 * route map of the Api class and the middleware chain, so the question a
 * person asks is "which endpoint is broken", not "which line".
 */
final class LintIssue
{
    public const ERROR = 'error';

    public const WARNING = 'warning';

    /**
     * @param  string  $severity  self::ERROR or self::WARNING
     * @param  string  $rule  A stable slug, so a report can be diffed between runs
     * @param  string  $where  The endpoint or class the finding belongs to
     * @param  string  $message  What is wrong, in one sentence
     * @param  string|null  $hint  What to do about it, when it is not obvious
     */
    public function __construct(
        public readonly string $severity,
        public readonly string $rule,
        public readonly string $where,
        public readonly string $message,
        public readonly ?string $hint = null
    ) {
    }

    public static function error(string $rule, string $where, string $message, ?string $hint = null): self
    {
        return new self(self::ERROR, $rule, $where, $message, $hint);
    }

    public static function warning(string $rule, string $where, string $message, ?string $hint = null): self
    {
        return new self(self::WARNING, $rule, $where, $message, $hint);
    }

    public function isError(): bool
    {
        return $this->severity === self::ERROR;
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'severity' => $this->severity,
            'rule' => $this->rule,
            'where' => $this->where,
            'message' => $this->message,
            'hint' => $this->hint,
        ];
    }
}
