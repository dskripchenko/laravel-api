<?php

namespace Components\LaravelApiExample\Versions\v1_1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use \Components\LaravelApiExample\Versions\v1\Controllers\AController as BaseAController;

/**
 * Class AController
 * @package Components\LaravelApiExample\Versions\v1_1\Controllers
 */
class AController extends BaseAController
{

    /**
     * Method B
     * Method description
     *
     * @input integer $id Id
     * @input string $name2 Name
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
                'method' => 'A-b',
                'request' => [
                    $request->id,
                    $request->name2
                ]
            ]
        );
    }
}
