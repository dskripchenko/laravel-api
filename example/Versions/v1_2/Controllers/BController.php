<?php

namespace Components\LaravelApiExample\Versions\v1_2\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use \Components\LaravelApiExample\Versions\v1_1\Controllers\BController as BaseBController;

/**
 * Class BController
 * @package Components\LaravelApiExample\Versions\v1_2\Controllers
 */
class BController extends BaseBController
{
    /**
     * Method C
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
    public function c(Request $request): JsonResponse
    {
        return $this->success(
            [
                'method' => 'B-c',
                'request' => [
                    $request->id,
                    $request->name
                ]
            ]
        );
    }
}
