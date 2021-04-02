<?php

namespace Components\LaravelApiExample\Versions\v1\Controllers;

use Components\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class AController
 * @package Components\LaravelApiExample\Versions\v1\Controllers
 */
class AController extends ApiController
{
    /**
     * Method A
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
    public function a(Request $request): JsonResponse
    {
        return $this->success(
            [
                'method' => 'A-a',
                'request' => [
                    $request->input('id'),
                    $request->input('name')
                ]
            ]
        );
    }

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
                'method' => 'A-b',
                'request' => [
                    $request->input('id'),
                    $request->input('name')
                ]
            ]
        );
    }

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
                'method' => 'A-c',
                'request' => [
                    $request->input('id'),
                    $request->input('name')
                ]
            ]
        );
    }
}
