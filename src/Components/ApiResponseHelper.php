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
    public static function sayError($data = []){
        return static::say(array_merge_deep($data, ['success' => false]));
    }
}