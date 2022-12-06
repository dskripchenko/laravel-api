<?php

namespace Dskripchenko\LaravelApi\Controllers;

use Dskripchenko\LaravelApi\Services\ApiResponseHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;

/**
 * Class ApiController
 * @package Dskripchenko\LaravelApi\Controllers
 */
class ApiController extends Controller
{
    /**
     * @param $payload
     * @param $status
     * @return JsonResponse
     */
    public function success($payload = [], $status = 200): JsonResponse
    {
        if ($this->isPayloadJsonResource($payload)) {
            $payload = $payload->toArray(request());
        }
        return ApiResponseHelper::say($payload, $status);
    }

    /**
     * @param  mixed  $payload
     * @return JsonResponse
     */
    public function error($payload = [], $status = 200): JsonResponse
    {
        if ($this->isPayloadJsonResource($payload)) {
            $payload = $payload->toArray(request());
        }
        return ApiResponseHelper::sayError($payload, $status);
    }

    protected function isPayloadJsonResource($payload): bool
    {
        return is_object($payload) && is_subclass_of($payload, JsonResource::class);
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
