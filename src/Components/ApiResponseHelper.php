<?php


namespace Dskripchenko\LaravelApi\Components;


class ApiResponseHelper
{
    /**
     * @param array $data
     * @return array
     */
    public static function say($data = []){
        return ArrayMergeHelper::merge(['success' => true], $data);
    }

    /**
     * @param array $data
     * @return array
     */
    public static function sayError($data = []){
        return static::say(ArrayMergeHelper::merge($data, ['success' => false]));
    }
}