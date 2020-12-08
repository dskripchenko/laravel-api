<?php


namespace Dskripchenko\LaravelApiExample\Versions\v2\Controllers;


use Illuminate\Http\Request;

class DController
{
    /**
     * Method D
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
    public function d(Request $request)
    {
        return ['method' => 'D-d', 'request' => [$request->id, $request->name]];
    }
}
