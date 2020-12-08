<?php


namespace Dskripchenko\LaravelApiExample\Versions\v1_2\Controllers;

use Illuminate\Http\Request;

class BController extends \Dskripchenko\LaravelApiExample\Versions\v1_1\Controllers\BController
{
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
    public function c(Request $request)
    {
        return ['method' => 'B-c', 'request' => [$request->id, $request->name]];
    }
}
