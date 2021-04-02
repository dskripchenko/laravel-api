<?php

namespace Dskripchenko\LaravelApi\Controllers;

use Dskripchenko\LaravelApi\Services\ApiResponseHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Class ApiController
 * @package Dskripchenko\LaravelApi\Controllers
 */
class ApiController extends Controller
{
    /**
     * @param array $payload
     * @return JsonResponse
     */
    public function success($payload = []): JsonResponse
    {
        return ApiResponseHelper::say($payload);
    }

    /**
     * @param array $payload
     * @return JsonResponse
     */
    public function error($payload = []): JsonResponse
    {
        return ApiResponseHelper::sayError($payload);
    }

    /**
     * @param $messages
     * @return JsonResponse
     */
    public function validationError($messages): JsonResponse
    {
        return $this->error([
            'errorKey' => 'validation',
            'messages' => $messages
        ]);
    }
}
