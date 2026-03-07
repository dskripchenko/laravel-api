<?php

namespace Dskripchenko\LaravelApiExample\Versions\v1;

use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApiExample\Versions\v1\Controllers\AController;

/**
 * Class Api
 * @package Dskripchenko\LaravelApiExample\Versions\v1
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
