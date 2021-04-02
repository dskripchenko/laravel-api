<?php

namespace Components\LaravelApiExample\Versions\v1_1\Controllers;

use Components\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class BController
 * @package Components\LaravelApiExample\Versions\v1_1\Controllers
 */
class BController extends ApiController
{
    /**
     * Method B
     * Method description
     *
     * @input integer $id Id
     * @input string $name Name
     *
     * @output string $method Method
     * @output array $request Request data
     *
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function b(Request $request): JsonResponse
    {
        return $this->success(
            [
                'method' => 'B-b',
                'request' => [
                    $request->id,
                    $request->name
                ]
            ]
        );
    }
}
