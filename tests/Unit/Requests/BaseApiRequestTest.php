<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Exceptions\ApiException;
use Dskripchenko\LaravelApi\Requests\BaseApiRequest;

it('throws when uri pattern missing version', function () {
    $request = new BaseApiRequest();
    $ref = new \ReflectionProperty($request, 'apiUriPattern');
    $ref->setAccessible(true);
    $ref->setValue($request, '{controller}/{action}');

    $method = new \ReflectionMethod($request, 'validateApiUriPattern');
    $method->setAccessible(true);
    $method->invoke($request);
})->throws(ApiException::class, 'версия {version}');

it('throws when uri pattern missing controller', function () {
    $request = new BaseApiRequest();
    $ref = new \ReflectionProperty($request, 'apiUriPattern');
    $ref->setAccessible(true);
    $ref->setValue($request, '{version}/{action}');

    $method = new \ReflectionMethod($request, 'validateApiUriPattern');
    $method->setAccessible(true);
    $method->invoke($request);
})->throws(ApiException::class, 'контроллер {controller}');

it('throws when uri pattern missing action', function () {
    $request = new BaseApiRequest();
    $ref = new \ReflectionProperty($request, 'apiUriPattern');
    $ref->setAccessible(true);
    $ref->setValue($request, '{version}/{controller}');

    $method = new \ReflectionMethod($request, 'validateApiUriPattern');
    $method->setAccessible(true);
    $method->invoke($request);
})->throws(ApiException::class, 'экшен {action}');

it('validates correct uri pattern without error', function () {
    $request = new BaseApiRequest();
    $ref = new \ReflectionProperty($request, 'apiUriPattern');
    $ref->setAccessible(true);
    $ref->setValue($request, '{version}/{controller}/{action}');

    $method = new \ReflectionMethod($request, 'validateApiUriPattern');
    $method->setAccessible(true);

    expect(fn() => $method->invoke($request))->not->toThrow(ApiException::class);
});

it('parses version controller action from URI', function () {
    $symfonyRequest = \Symfony\Component\HttpFoundation\Request::create('/api/v1/item/list', 'GET');
    $request = new BaseApiRequest();

    $refPrefix = new \ReflectionProperty($request, 'apiPrefix');
    $refPrefix->setAccessible(true);
    $refPrefix->setValue($request, 'api');

    $refPattern = new \ReflectionProperty($request, 'apiUriPattern');
    $refPattern->setAccessible(true);
    $refPattern->setValue($request, '{version}/{controller}/{action}');

    // Initialize from symfony request
    $request->initialize(
        $symfonyRequest->query->all(),
        $symfonyRequest->request->all(),
        [],
        $symfonyRequest->cookies->all(),
        [],
        $symfonyRequest->server->all()
    );

    $method = new \ReflectionMethod($request, 'prepareApi');
    $method->setAccessible(true);
    $method->invoke($request);

    $refVersion = new \ReflectionProperty($request, 'apiVersion');
    $refVersion->setAccessible(true);
    expect($refVersion->getValue($request))->toBe('v1');

    $refController = new \ReflectionProperty($request, 'apiController');
    $refController->setAccessible(true);
    expect($refController->getValue($request))->toBe('item');

    $refAction = new \ReflectionProperty($request, 'apiAction');
    $refAction->setAccessible(true);
    expect($refAction->getValue($request))->toBe('list');
});

it('does not parse when prefix is absent from URI', function () {
    $symfonyRequest = \Symfony\Component\HttpFoundation\Request::create('/other/v1/item/list', 'GET');
    $request = new BaseApiRequest();

    $refPrefix = new \ReflectionProperty($request, 'apiPrefix');
    $refPrefix->setAccessible(true);
    $refPrefix->setValue($request, 'api');

    $refPattern = new \ReflectionProperty($request, 'apiUriPattern');
    $refPattern->setAccessible(true);
    $refPattern->setValue($request, '{version}/{controller}/{action}');

    $request->initialize(
        $symfonyRequest->query->all(),
        $symfonyRequest->request->all(),
        [],
        $symfonyRequest->cookies->all(),
        [],
        $symfonyRequest->server->all()
    );

    $method = new \ReflectionMethod($request, 'prepareApi');
    $method->setAccessible(true);
    $method->invoke($request);

    $refVersion = new \ReflectionProperty($request, 'apiVersion');
    $refVersion->setAccessible(true);
    expect($refVersion->getValue($request))->toBeNull();
});

it('does not parse when segment count does not match', function () {
    $symfonyRequest = \Symfony\Component\HttpFoundation\Request::create('/api/v1/item', 'GET');
    $request = new BaseApiRequest();

    $refPrefix = new \ReflectionProperty($request, 'apiPrefix');
    $refPrefix->setAccessible(true);
    $refPrefix->setValue($request, 'api');

    $refPattern = new \ReflectionProperty($request, 'apiUriPattern');
    $refPattern->setAccessible(true);
    $refPattern->setValue($request, '{version}/{controller}/{action}');

    $request->initialize(
        $symfonyRequest->query->all(),
        $symfonyRequest->request->all(),
        [],
        $symfonyRequest->cookies->all(),
        [],
        $symfonyRequest->server->all()
    );

    $method = new \ReflectionMethod($request, 'prepareApi');
    $method->setAccessible(true);
    $method->invoke($request);

    $refVersion = new \ReflectionProperty($request, 'apiVersion');
    $refVersion->setAccessible(true);
    expect($refVersion->getValue($request))->toBeNull();
});

it('returns lowercase http method', function () {
    $symfonyRequest = \Symfony\Component\HttpFoundation\Request::create('/api/v1/item/list', 'POST');

    $ref = new \ReflectionProperty(BaseApiRequest::class, '_instance');
    $ref->setAccessible(true);
    $ref->setValue(null, BaseApiRequest::createFromBase($symfonyRequest));

    $instance = $ref->getValue();
    expect($instance->method())->toBe('post');
});

it('returns singleton instance on repeated calls', function () {
    $instance1 = BaseApiRequest::getInstance();
    $instance2 = BaseApiRequest::getInstance();
    expect($instance1)->toBe($instance2);
});

it('returns empty rules by default', function () {
    $request = new BaseApiRequest();
    expect($request->rules())->toBe([]);
});
