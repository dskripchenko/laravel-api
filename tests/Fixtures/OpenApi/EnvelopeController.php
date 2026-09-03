<?php

declare(strict_types=1);

namespace Tests\Fixtures\OpenApi;

use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnvelopeController extends ApiController
{
    /**
     * Is prolongation available
     *
     * @input string $uuid Agreement
     *
     * @output {ProlongationAvailable}
     */
    public function available(Request $request): JsonResponse
    {
        return $this->success([]);
    }

    /**
     * Fields, the flat way
     *
     * @output string $url Where to go next
     * @output string ?$comment Free text
     */
    public function fields(Request $request): JsonResponse
    {
        return $this->success([]);
    }

    /**
     * Create with explicit responses
     *
     * @input string $uuid Agreement
     *
     * @response 201 {ProlongationAvailable}
     * @response 422 {ValidationError}
     * @response 404 Not found
     */
    public function create(Request $request): JsonResponse
    {
        return $this->created([]);
    }

    /**
     * Nothing documented
     */
    public function plain(Request $request): JsonResponse
    {
        return $this->success([]);
    }
}
