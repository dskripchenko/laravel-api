<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Exceptions\ApiException;

it('stores errorKey and message', function () {
    $e = new ApiException('test_error', 'Something went wrong');
    expect($e->getErrorKey())->toBe('test_error');
    expect($e->getMessage())->toBe('Something went wrong');
});

it('stores exception code', function () {
    $e = new ApiException('err', 'msg', 42);
    expect($e->getCode())->toBe(42);
});

it('stores previous exception', function () {
    $prev = new \RuntimeException('prev');
    $e = new ApiException('err', 'msg', 0, $prev);
    expect($e->getPrevious())->toBe($prev);
});

it('is an instance of Exception', function () {
    $e = new ApiException('err', 'msg');
    expect($e)->toBeInstanceOf(\Exception::class);
});
