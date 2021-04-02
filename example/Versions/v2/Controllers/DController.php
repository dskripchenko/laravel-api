<?php

namespace Components\LaravelApiExample\Versions\v2\Controllers;

use Components\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class DController
 * @package Components\LaravelApiExample\Versions\v2\Controllers
 */
class DController extends ApiController
{
    /**
     * Method D
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
    public function d(Request $request): JsonResponse
    {
        return $this->success(
            [
                'method' => 'D-d',
                'request' => [$request->id, $request->name]
            ]
        );
    }
}
