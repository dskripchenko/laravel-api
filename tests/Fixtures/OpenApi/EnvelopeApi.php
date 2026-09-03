<?php

declare(strict_types=1);

namespace Tests\Fixtures\OpenApi;

use Dskripchenko\LaravelApi\Components\BaseApi;

/**
 * Envelope API
 * API whose responses are documented in the {success, payload} envelope
 */
class EnvelopeApi extends BaseApi
{
    public static $useResponseTemplates = true;

    public static $responseEnvelope = true;

    public static function getMethods(): array
    {
        return [
            'controllers' => [
                'agreement' => [
                    'controller' => EnvelopeController::class,
                    'actions' => [
                        'available' => ['method' => 'get'],
                        'fields' => ['method' => 'get'],
                        'create' => ['method' => 'post'],
                        'plain' => ['method' => 'get'],
                    ],
                ],
            ],
        ];
    }

    public static function getOpenApiTemplates(): array
    {
        return [
            // A payload, the way templates are written with the envelope on.
            'ProlongationAvailable' => [
                'url' => 'string',
                'uuid' => 'string!',
                'clientEmail' => 'string(email)',
                'clientPhone' => 'string',
            ],
            'ValidationError' => [
                'errorKey' => 'string!',
                'message' => 'string!',
                'errors' => ['type' => 'object'],
            ],
            // A hand-written envelope — what people wrote before the option
            // existed. The linter warns: it gets wrapped a second time.
            'LegacyResult' => [
                'success' => 'boolean!',
                'payload' => '@ProlongationAvailable',
            ],
        ];
    }
}
