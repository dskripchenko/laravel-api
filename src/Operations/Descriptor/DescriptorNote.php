<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Operations\Descriptor;

/**
 * Something the builder had to leave out of a descriptor.
 *
 * What a `[method]` returns is only known once it is called, so the linter
 * cannot see it in the docblock: a result that is not an array, a line that
 * does not parse, a template that does not exist — each is dropped from the
 * spec without a word. The builder writes it down here instead, and the
 * linter reports it.
 */
final class DescriptorNote
{
    /**
     * @param  string  $rule  `callable.not-array`, `callable.non-string`, `callable.malformed-line`, `callable.unknown-template`.
     * @param  string  $message  What was left out, and why.
     */
    public function __construct(
        public readonly string $rule,
        public readonly string $message,
    ) {
    }
}
