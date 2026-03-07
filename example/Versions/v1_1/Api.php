<?php

namespace Dskripchenko\LaravelApiExample\Versions\v1_1;

use Dskripchenko\LaravelApiExample\Versions\v1_1\Controllers\AController;
use Dskripchenko\LaravelApiExample\Versions\v1_1\Controllers\BController;

/**
 * Class Api
 * @package Dskripchenko\LaravelApiExample\Versions\v1_1
 */
class Api extends \Dskripchenko\LaravelApiExample\Versions\v1\Api
{
    public static function getMethods(): array
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
