<?php

declare(strict_types=1);

namespace Tests\Fixtures\OpenApi;

use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AllOptionalOutputController extends ApiController
{
    /**
     * All optional output
     * All output fields are optional
     *
     * @output string ?$foo Foo field
     * @output string ?$bar Bar field
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function allOptional(Request $request): JsonResponse
    {
        return $this->success([]);
    }
}
