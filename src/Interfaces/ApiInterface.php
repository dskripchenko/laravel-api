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
     *               //TODO исключить все middleware на уровне экшена
     *              'exclude-all-middleware' => true,
     *          ],
     *          'login' => [],
     *          'logout' => false,
     *          'limited-access' => [
     *              'action' => 'limitedAccess',
     *              'middleware' => [
     *                  VerifyApiToken::class
     *              ]
     *          ],
     *          'get-sign' => 'getSign',
     *          'checkSign' => [
     *               //TODO middleware на уровне экшена
     *              'middleware' => [
     *                  VerifyApiSign::class
     *              ],
     *               //TODO исключить middleware для контроллера
     *              'exclude-middleware' => [],
     *          ],
     *       ],
     *        //TODO исключить все middleware для контроллера
     *       'exclude-all-middleware' => true,
     *        //TODO сквозные middleware на уровне контроллера
     *       'middleware' => [],
     *        //TODO исключить middleware для контроллера
     *       'exclude-middleware' => [],
     *   ]
     * ],
     *  //TODO сквозные middleware на уровне всего апи
     * 'middleware' => []
     */
    public static function getMethods(): array;
}