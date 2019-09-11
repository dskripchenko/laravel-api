<?php


namespace Dskripchenko\LaravelApiExample\Versions\v1\Controllers;


use Dskripchenko\LaravelApi\Facades\ApiRequest;
use Illuminate\Routing\Controller;

class AController extends Controller
{
    /**
     * Method A
     * Method description
     *
     * @input integer $id Id
     * @input string $name Name
     *
     * @output string $method Method
     * @output array $request Request data
     *
     *
     * @param ApiRequest $request
     * @return array
     */
    public function a(ApiRequest $request){
        return ['method' => 'A-a', 'request' => [$request->id, $request->name]];
    }

    /**
     * Method B
     * Method description
     *
     * @input integer $id Id
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
        return ['method' => 'A-b', 'request' => [$request->id, $request->name]];
    }

    /**
     * Method C
     * Method description
     *
     * @input integer $id Id
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
        return ['method' => 'A-c', 'request' => [$request->id, $request->name]];
    }
}