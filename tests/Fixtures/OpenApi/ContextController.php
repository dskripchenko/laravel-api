<?php

declare(strict_types=1);

namespace Tests\Fixtures\OpenApi;

use Dskripchenko\LaravelApi\Controllers\ApiController;
use Dskripchenko\LaravelApi\Services\OpenApi\OperationContext;
use Illuminate\Http\JsonResponse;

/**
 * A generic controller: which fields an action takes depends on the
 * controller key the route was registered under.
 */
class ContextController extends ApiController
{
    /** @var array<string, array<string, array<string, mixed>>> */
    public const FIELDS = [
        'users' => [
            'email' => ['type' => 'string', 'format' => 'email', 'maxLength' => 255],
            'age' => ['type' => 'integer', 'minimum' => 0, 'nullable' => true],
        ],
        'posts' => [
            'title' => ['type' => 'string'],
            'status' => ['type' => 'string', 'enum' => ['draft', 'published']],
        ],
    ];

    /**
     * Create an entity
     *
     * @input [entityFields]
     *
     * @output [entityOutput]
     */
    public function create(): JsonResponse
    {
        return $this->success();
    }

    /**
     * Update an entity
     *
     * @input integer $id Identifier
     * @input [entityFields]
     */
    public function update(): JsonResponse
    {
        return $this->success();
    }

    /**
     * Search entities
     *
     * @input integer ?$page Page
     * @input [entityFields]
     */
    public function search(): JsonResponse
    {
        return $this->success();
    }

    /**
     * Show an entity
     *
     * @input integer $id Identifier
     *
     * @output integer $id Identifier
     * @output [entityOutputLines]
     */
    public function show(): JsonResponse
    {
        return $this->success();
    }

    /**
     * The historical form: no context, a list of lines
     *
     * @input [legacyLines]
     */
    public function legacy(): JsonResponse
    {
        return $this->success();
    }

    /**
     * Scalars by name rather than the object
     *
     * @input [scalarLines]
     */
    public function scalars(): JsonResponse
    {
        return $this->success();
    }

    /**
     * @return array<string, mixed>
     */
    public function entityFields(OperationContext $context): array
    {
        $required = $context->actionKey === 'create'
            ? array_keys(array_filter(self::FIELDS[$context->controllerKey], static fn (array $f): bool => empty($f['nullable'])))
            : [];

        return array_filter([
            'type' => 'object',
            'properties' => self::FIELDS[$context->controllerKey],
            'required' => $required,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function entityOutput(OperationContext $context): array
    {
        return [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'integer']] + self::FIELDS[$context->controllerKey],
            'required' => ['id'],
            'x-tag' => $context->tag,
        ];
    }

    /**
     * @return string[]
     */
    public function entityOutputLines(string $controllerKey): array
    {
        return array_map(
            static fn (string $name): string => "string \${$name} From {$controllerKey}",
            array_keys(self::FIELDS[$controllerKey])
        );
    }

    /**
     * @return string[]
     */
    public function legacyLines(): array
    {
        return ['string $name Name', 'integer ?$count Count'];
    }

    /**
     * @return string[]
     */
    public function scalarLines(
        string $version,
        string $controllerKey,
        string $actionKey,
        string $httpMethod,
        OperationContext $operation,
        string $untouched = 'default'
    ): array {
        $flavour = (string) ($operation->actionOptions['flavour'] ?? 'none');

        return [
            "string \$marker {$version}|{$controllerKey}|{$actionKey}|{$httpMethod}|{$flavour}|{$untouched}|{$operation->controllerMethod}",
        ];
    }
}
