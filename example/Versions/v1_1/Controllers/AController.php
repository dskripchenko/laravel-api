<?php


namespace Dskripchenko\LaravelApiExample\Versions\v1_1\Controllers;


use Dskripchenko\LaravelApi\Facades\ApiRequest;

class AController extends \Dskripchenko\LaravelApiExample\Versions\v1\Controllers\AController
{

    /**
     * Method B
     * Method description
     *
     * @input indeger $id Id
     * @input string $name2 Name
     *
     * @output string $method Method
     * @output array $request Request data
     *
     *
     * @param ApiRequest $request
     * @return array
     */
    public function b(ApiRequest $request){
        return ['method' => 'A-b', 'request' => [$request->id, $request->name2]];
    }
}