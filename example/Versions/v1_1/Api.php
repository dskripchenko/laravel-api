<?php

namespace Dskripchenko\LaravelApiExample\Versions\v1_1;

use Dskripchenko\LaravelApiExample\Versions\v1_1\Controllers\AController;
use Dskripchenko\LaravelApiExample\Versions\v1_1\Controllers\BController;

class Api extends \Dskripchenko\LaravelApiExample\Versions\v1\Api
{
    protected static function getMethods()
    {
        return [
            'controllers' => [
                'a' => [
                    'controller' => AController::class,
                    'actions' => [
                        'a' => false
                    ]
                ],
                'b' => [
                    'controller' => BController::class,
                    'actions' => [
                        'b'
                    ]
                ]
            ]
        ];
    }
}
