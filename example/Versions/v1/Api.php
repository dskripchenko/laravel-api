<?php
namespace Dskripchenko\LaravelApiExample\Versions\v1;

use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApiExample\Versions\v1\Controllers\AController;

class Api extends BaseApi
{
    protected static function getMethods()
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