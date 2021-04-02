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
     * @param array $data
     * @return JsonResponse
     */
    public static function say($data = []): JsonResponse
    {
        $data = array_merge_deep(
            [
                'success' => Arr::pull($data, 'success', true)
            ],
            [
                'payload' => $data
            ]
        );
        return new JsonResponse($data);
    }

    /**
     * @param array $data
     * @return JsonResponse
     */
    public static function sayError($data = []): JsonResponse
    {
        return static::say(array_merge_deep($data, ['success' => false]));
    }
}
