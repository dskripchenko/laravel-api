<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Operations\Descriptor;

use Dskripchenko\LaravelApi\Operations\Doc\OperationDoc;
use Dskripchenko\LaravelApi\Operations\Operation;
use Dskripchenko\LaravelApi\Services\OpenApi\OperationContext;

/**
 * One operation, fully described: the action from getMethods(), what its
 * docblock says, and the schemas of its input and output.
 *
 * There is one per HTTP method of the action: a `[method]` is told which one
 * it describes and may answer differently.
 */
final class OperationDescriptor
{
    /**
     * @param  string  $httpMethod  The HTTP method, as written in getMethods().
     * @param  string  $operationId  `{controller}_{action}`, with `_{method}` for the second and later methods.
     * @param  array  $security  The action's `security`, or else its `@security` schemes.
     * @param  array[]  $headers  `@header` fields: name, description, required, type.
     * @param  OutputDeclaration|null  $output  The success payload; null when `@response` lists the answers instead.
     * @param  ResponseDeclaration[]  $responses  `@response` lines, in order.
     */
    public function __construct(
        public readonly Operation $operation,
        public readonly OperationDoc $doc,
        public readonly OperationContext $context,
        public readonly string $httpMethod,
        public readonly string $operationId,
        public readonly bool $deprecated,
        public readonly array $security,
        public readonly InputDeclaration $input,
        public readonly array $headers,
        public readonly ?OutputDeclaration $output,
        public readonly array $responses,
    ) {
    }

    public function summary(): string
    {
        return $this->doc->summary;
    }

    public function description(): string
    {
        return $this->doc->description;
    }

    /** Whether `@response` lists the answers, instead of `@output` describing the success. */
    public function hasExplicitResponses(): bool
    {
        return $this->output === null;
    }
}
