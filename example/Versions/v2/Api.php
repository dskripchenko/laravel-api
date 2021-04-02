<?php
namespace Components\LaravelApiExample\Versions\v2;

use Components\LaravelApi\Components\BaseApi;
use Components\LaravelApiExample\Versions\v2\Controllers\DController;

/**
 * Class Api
 * @package Components\LaravelApiExample\Versions\v2
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
