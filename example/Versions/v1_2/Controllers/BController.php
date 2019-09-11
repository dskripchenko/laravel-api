<?php


namespace Dskripchenko\LaravelApiExample\Versions\v1_2\Controllers;


use Dskripchenko\LaravelApi\Facades\ApiRequest;

class BController extends \Dskripchenko\LaravelApiExample\Versions\v1_1\Controllers\BController
{
    /**
     * Method C
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
    public function c(ApiRequest $request){
        return ['method' => 'B-c', 'request' => [$request->id, $request->name]];
    }
}