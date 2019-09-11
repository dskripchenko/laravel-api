<?php


namespace Dskripchenko\LaravelApiExample\Versions\v1_1\Controllers;


use Dskripchenko\LaravelApi\Facades\ApiRequest;

class BController
{
    /**
     * Method B
     * Method description
     *
     * @input indeger $id Id
     * @input string $name Name
     *
     * @output string $method Method
     * @output array $request Request data
     *
     *
     * @param ApiRequest $request
     * @return array
     */
    public function b(ApiRequest $request){
        return ['method' => 'B-b', 'request' => [$request->id, $request->name]];
    }
}