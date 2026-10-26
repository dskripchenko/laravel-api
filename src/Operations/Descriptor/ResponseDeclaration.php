<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Operations\Descriptor;

/**
 * One `@response` line: a status, and a template or a description.
 */
final class ResponseDeclaration
{
    /**
     * @param  string  $code  The three-digit status, as written.
     * @param  string|null  $template  The template the answer carries, if any.
     * @param  string  $description  The description of an answer without a template.
     */
    public function __construct(
        public readonly string $code,
        public readonly ?string $template,
        public readonly string $description,
    ) {
    }
}
