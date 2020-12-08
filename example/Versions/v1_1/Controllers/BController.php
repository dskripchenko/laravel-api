<?php


namespace Dskripchenko\LaravelApiExample\Versions\v1_1\Controllers;


use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BController extends Controller
{
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
    public function b(Request $request)
    {
        return ['method' => 'B-b', 'request' => [$request->id, $request->name]];
    }
}
