<?php

namespace Dskripchenko\LaravelApi\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

/**
 * Class ApiResponseHelper
 * @package Dskripchenko\LaravelApi\Services
 */
class ApiResponseHelper
{
    /**
     * @param $data
     * @param $status
     * @return JsonResponse
     */
    public static function say($data = [], $status = 200): JsonResponse
    {
        $data = array_merge_deep(
            [
                'success' => Arr::pull($data, 'success', true)
            ],
            [
                'payload' => $data
            ]
        );
        return new JsonResponse($data, $status);
    }

    /**
     * @param $data
     * @param $status
     * @return JsonResponse
     */
    public static function sayError($data = [], $status = 200): JsonResponse
    {
        return static::say(array_merge_deep($data, ['success' => false]), $status);
    }
}
