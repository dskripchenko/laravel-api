<?php


namespace Dskripchenko\LaravelApiExample\Versions\v2\Controllers;


use Dskripchenko\LaravelApi\Facades\ApiRequest;

class DController
{
    /**
     * Method D
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
    public function d(ApiRequest $request){
        return ['method' => 'D-d', 'request' => [$request->id, $request->name]];
    }
}