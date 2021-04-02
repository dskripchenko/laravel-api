<?php

namespace Components\LaravelApiExample\Versions\v1_1;

use Components\LaravelApiExample\Versions\v1_1\Controllers\AController;
use Components\LaravelApiExample\Versions\v1_1\Controllers\BController;

/**
 * Class Api
 * @package Components\LaravelApiExample\Versions\v1_1
 */
class Api extends \Components\LaravelApiExample\Versions\v1\Api
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
