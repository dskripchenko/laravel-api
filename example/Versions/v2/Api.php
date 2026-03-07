<?php
namespace Dskripchenko\LaravelApiExample\Versions\v2;

use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApiExample\Versions\v2\Controllers\DController;

/**
 * Class Api
 * @package Dskripchenko\LaravelApiExample\Versions\v2
 */
class Api extends BaseApi
{
    public static function getMethods(): array
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
