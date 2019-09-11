<?php
namespace Dskripchenko\LaravelApiExample\Versions\v2;

use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApiExample\Versions\v2\Controllers\DController;

class Api extends BaseApi
{
    protected static function getMethods()
    {
        return [
            'controllers' => [
                'd' => [
                    'controller' => DController::class,
                    'actions' => [
                        'd'
                    ]
                ]
            ]
        ];
    }
}