<?php

namespace Components\LaravelApiExample\Versions\v1;

use Components\LaravelApi\Components\BaseApi;
use Components\LaravelApiExample\Versions\v1\Controllers\AController;

/**
 * Class Api
 * @package Components\LaravelApiExample\Versions\v1
 */
class Api extends BaseApi
{
    public static function getMethods(): array
    {
        return [
            'controllers' => [
                'a' => [
                    'controller' => AController::class,
                    'actions' => [
                        'a', 'b', 'c'
                    ]
                ]
            ]
        ];
    }
}
