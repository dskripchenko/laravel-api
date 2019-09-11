<?php


namespace Dskripchenko\LaravelApiExample\Versions\v1\Controllers;


use Illuminate\Http\Request;
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
     * @param Request $request
     * @return array
     */
    public function a(Request $request){
        return ['method' => 'A-a', 'request' => [$request->input('id'), $request->input('name')]];
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
     * @param Request $request
     * @return array
     */
    public function b(Request $request){
        return ['method' => 'A-b', 'request' => [$request->input('id'), $request->input('name')]];
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
     * @param Request $request
     * @return array
     */
    public function c(Request $request){
        return ['method' => 'A-c', 'request' => [$request->input('id'), $request->input('name')]];
    }
}