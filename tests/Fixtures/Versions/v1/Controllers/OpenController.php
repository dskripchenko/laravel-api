<?php

declare(strict_types=1);

namespace Tests\Fixtures\Versions\v1\Controllers;

use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class OpenController extends ApiController
{
    /**
     * Ping
     * Health check endpoint.
     * Details: {@see \Tests\Fixtures\Versions\v1\Controllers\ItemController::list()}
     * Docs: {@link https://example.test/health status page}
     * {@inheritDoc}
     *
     * @output string $pong Pong response
     *
     * @return JsonResponse
     */
    public function ping(): JsonResponse
    {
        return $this->success(['pong' => true]);
    }
}
