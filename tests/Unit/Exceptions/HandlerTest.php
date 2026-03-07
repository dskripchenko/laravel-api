<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Exceptions\Handler;
use Illuminate\Http\Request;

it('delegates to ApiErrorHandler for api-endpoint route', function () {
    $handler = app(\Illuminate\Contracts\Debug\ExceptionHandler::class);
    expect($handler)->toBeInstanceOf(Handler::class);

    // Create a request that matches the api-endpoint route
    $request = Request::create('/api/v1/item/list', 'GET');
    $request->setRouteResolver(function () {
        $route = new \Illuminate\Routing\Route('GET', 'api/{version}/{controller}/{action}', fn() => null);
        $route->name('api-endpoint');
        $route->bind(app('request'));
        return $route;
    });

    $e = new \RuntimeException('test error');
    $response = $handler->render($request, $e);
    $data = json_decode($response->getContent(), true);
    expect($data['success'])->toBeFalse();
    expect($data['payload']['message'])->toBe('test error');
});

it('delegates to parent for non-api routes', function () {
    $handler = app(\Illuminate\Contracts\Debug\ExceptionHandler::class);

    $request = Request::create('/web/page', 'GET');
    $e = new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Not found');

    $response = $handler->render($request, $e);
    expect($response->getStatusCode())->toBe(404);
});

it('handles Throwable exceptions', function () {
    $handler = app(\Illuminate\Contracts\Debug\ExceptionHandler::class);

    $request = Request::create('/api/v1/item/list', 'GET');
    $request->setRouteResolver(function () {
        $route = new \Illuminate\Routing\Route('GET', 'api/{version}/{controller}/{action}', fn() => null);
        $route->name('api-endpoint');
        $route->bind(app('request'));
        return $route;
    });

    $e = new \DivisionByZeroError('division by zero');
    $response = $handler->render($request, $e);
    $data = json_decode($response->getContent(), true);
    expect($data['success'])->toBeFalse();
});
