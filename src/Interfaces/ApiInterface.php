<?php

namespace Dskripchenko\LaravelApi\Interfaces;

interface ApiInterface
{
    /**
     * @return array
     *
     *
     * 'controllers' => [
     *   'user' => [
     *       'controller' => \App\Api\Versions\v1_0\Controllers\UserController::class,
     *       'actions' => [
     *          'register' => [
     *              'method' => 'put',
     *               //TODO exclude every middleware at the action level
     *              'exclude-all-middleware' => true,
     *          ],
     *          'login' => [],
     *          'logout' => false,
     *          'limited-access' => [
     *              'method' => ['get', 'post'],
     *              'action' => 'limitedAccess',
     *              'middleware' => [
     *                  VerifyApiToken::class
     *              ]
     *          ],
     *          'get-sign' => 'getSign',
     *          'checkSign' => [
     *               //TODO middleware at the action level
     *              'middleware' => [
     *                  VerifyApiSign::class
     *              ],
     *               //TODO exclude middleware for the controller
     *              'exclude-middleware' => [],
     *          ],
     *       ],
     *        //TODO exclude every middleware for the controller
     *       'exclude-all-middleware' => true,
     *        //TODO shared middleware at the controller level
     *       'middleware' => [],
     *        //TODO exclude middleware for the controller
     *       'exclude-middleware' => [],
     *   ]
     * ],
     *  //TODO shared middleware across the whole API
     * 'middleware' => []
     */
    public static function getMethods(): array;
}