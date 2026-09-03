<?php

declare(strict_types=1);

namespace Tests\Fixtures\OpenApi;

/**
 * Custom envelope API
 * The same endpoints under an envelope of the application's own shape
 */
class CustomEnvelopeApi extends EnvelopeApi
{
    public static $responseEnvelope = [
        'ok!' => 'boolean Whether the call succeeded',
        'data!' => '{payload}',
        'meta' => '@Meta',
    ];

    public static function getOpenApiTemplates(): array
    {
        return parent::getOpenApiTemplates() + [
            'Meta' => [
                'requestId' => 'string(uuid)!',
            ],
            // An own Error is the payload of the error answer.
            'Error' => [
                'errorKey' => 'string!',
                'message' => 'string!',
                'trace' => 'string',
            ],
        ];
    }
}
