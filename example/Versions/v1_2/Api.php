<?php

namespace Components\LaravelApiExample\Versions\v1_2;

use Components\LaravelApiExample\Versions\v1_2\Controllers\BController;

/**
 * Class Api
 * @package Components\LaravelApiExample\Versions\v1_2
 */
class Api extends \Components\LaravelApiExample\Versions\v1_1\Api
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
