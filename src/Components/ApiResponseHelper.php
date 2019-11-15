<?php


namespace Dskripchenko\LaravelApi\Components;


use Illuminate\Support\Arr;

class ApiResponseHelper
{
    /**
     * @param array $data
     * @return array
     */
    public static function say($data = []){
        return ArrayMergeHelper::merge(
            [
                'success' => Arr::pull($data, 'success', true)
            ],
            [
                'payload' => $data
            ]
        );
    }

    /**
     * @param array $data
     * @return array
     */
    public static function sayError($data = []){
        return static::say(ArrayMergeHelper::merge($data, ['success' => false]));
    }
}