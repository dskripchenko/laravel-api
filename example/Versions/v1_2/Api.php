<?php


namespace Dskripchenko\LaravelApiExample\Versions\v1_2;


use Dskripchenko\LaravelApiExample\Versions\v1_2\Controllers\BController;

class Api extends \Dskripchenko\LaravelApiExample\Versions\v1_1\Api
{
    protected static function getMethods()
    {
        return [
            'controllers' => [
                'a' => [
                    'actions' => [
                        'a' => true
                    ]
                ],
                'b' => [
                    'controller' => BController::class,
                    'actions' => [
                        'c'
                    ]
                ]
            ]
        ];
    }
}
