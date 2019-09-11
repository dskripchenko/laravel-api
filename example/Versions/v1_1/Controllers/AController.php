<?php


namespace Dskripchenko\LaravelApiExample\Versions\v1_1\Controllers;


use Illuminate\Http\Request;

class AController extends \Dskripchenko\LaravelApiExample\Versions\v1\Controllers\AController
{

    /**
     * Method B
     * Method description
     *
     * @input integer $id Id
     * @input string $name2 Name
     *
     * @output string $method Method
     * @output array $request Request data
     *
     *
     * @param Request $request
     * @return array
     */
    public function b(Request $request){
        return ['method' => 'A-b', 'request' => [$request->id, $request->name2]];
    }
}