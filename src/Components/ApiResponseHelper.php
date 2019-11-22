<?php


namespace Dskripchenko\LaravelApi\Components;


use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class ApiResponseHelper
{
    /**
     * @param array $data
     * @return JsonResponse
     */
    public static function say($data = []){
        $data = ArrayMergeHelper::merge(
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
    public static function sayError($data = []){
        return static::say(ArrayMergeHelper::merge($data, ['success' => false]));
    }
}