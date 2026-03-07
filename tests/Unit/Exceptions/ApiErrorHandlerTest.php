<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Exceptions\ApiErrorHandler;
use Dskripchenko\LaravelApi\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;

it('handles ApiException with errorKey and message', function () {
    $handler = new ApiErrorHandler();
    $e = new ApiException('test_key', 'Test message');
    $response = $handler->handle($e);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    $data = $response->getData(true);
    expect($data['success'])->toBeFalse();
    expect($data['payload']['errorKey'])->toBe('test_key');
    expect($data['payload']['message'])->toBe('Test message');
});

it('falls back to generic handler for unknown exceptions in debug mode', function () {
    app()['config']->set('app.debug', true);
    $handler = new ApiErrorHandler();
    $e = new \RuntimeException('Runtime error');
    $response = $handler->handle($e);

    $data = $response->getData(true);
    expect($data['success'])->toBeFalse();
    expect($data['payload']['message'])->toBe('Runtime error');
    expect($response->getStatusCode())->toBe(500);
});

it('hides exception details in production mode', function () {
    app()['config']->set('app.debug', false);
    $handler = new ApiErrorHandler();
    $e = new \RuntimeException('Sensitive SQL error: SELECT * FROM users');
    $response = $handler->handle($e);

    $data = $response->getData(true);
    expect($data['success'])->toBeFalse();
    expect($data['payload']['message'])->toBe('Internal server error');
    expect($data['payload']['message'])->not->toContain('SQL');
    expect($response->getStatusCode())->toBe(500);
});

it('allows adding custom handler', function () {
    $handler = new ApiErrorHandler();
    $handler->addErrorHandler(\InvalidArgumentException::class, function (\InvalidArgumentException $e) {
        return new JsonResponse(['custom' => true, 'msg' => $e->getMessage()]);
    });

    $response = $handler->handle(new \InvalidArgumentException('bad arg'));
    $data = $response->getData(true);
    expect($data['custom'])->toBeTrue();
    expect($data['msg'])->toBe('bad arg');
});

it('overwrites handler for same exception class', function () {
    $handler = new ApiErrorHandler();
    $handler->addErrorHandler(ApiException::class, function (ApiException $e) {
        return new JsonResponse(['overwritten' => true]);
    });

    $e = new ApiException('key', 'msg');
    $response = $handler->handle($e);
    $data = $response->getData(true);
    expect($data['overwritten'])->toBeTrue();
});

it('matches subclass by parent handler via class_parents traversal', function () {
    $handler = new ApiErrorHandler();
    $handler->addErrorHandler(\Exception::class, function (\Exception $e) {
        return new JsonResponse(['matched_parent' => true]);
    });

    $response = $handler->handle(new \RuntimeException('sub'));
    $data = $response->getData(true);
    expect($data['matched_parent'])->toBeTrue();
});

it('returns JsonResponse from default handler', function () {
    $handler = new ApiErrorHandler();
    $response = $handler->handle(new \LogicException('logic'));
    expect($response)->toBeInstanceOf(JsonResponse::class);
});

it('handles exception with empty message', function () {
    $handler = new ApiErrorHandler();
    $e = new ApiException('empty', '');
    $response = $handler->handle($e);
    $data = $response->getData(true);
    expect($data['payload']['errorKey'])->toBe('empty');
    expect($data['payload']['message'])->toBe('');
});
