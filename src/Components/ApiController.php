<?php


namespace Dskripchenko\LaravelApi\Components;


use Illuminate\Routing\Controller;

class ApiController extends Controller
{
    public function success($payload = [])
    {
        return ApiResponseHelper::say($payload);
    }

    public function validationError($messages)
    {
        return $this->error(
            [
                'errorKey' => 'validation',
                'messages' => $messages
            ]
        );
    }

    public function error($payload = [])
    {
        return ApiResponseHelper::sayError($payload);
    }
}
