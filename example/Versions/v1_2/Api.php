<?php

namespace Dskripchenko\LaravelApiExample\Versions\v1_2;

use Dskripchenko\LaravelApiExample\Versions\v1_2\Controllers\BController;

/**
 * Class Api
 * @package Dskripchenko\LaravelApiExample\Versions\v1_2
 */
class Api extends \Dskripchenko\LaravelApiExample\Versions\v1_1\Api
{
    public static function getMethods(): array
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
