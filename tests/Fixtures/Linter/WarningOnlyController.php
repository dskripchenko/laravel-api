<?php

declare(strict_types=1);

namespace Tests\Fixtures\Linter;

use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class WarningOnlyController extends ApiController
{
    /**
     * An unknown type is a warning: the generator carries on and calls it a
     * string, so the spec is wrong but nothing is broken.
     *
     * @input datetime $when When
     */
    public function unknownType(): JsonResponse
    {
        return $this->success();
    }
}
