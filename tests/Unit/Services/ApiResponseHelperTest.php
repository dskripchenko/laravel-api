<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Services\ApiResponseHelper;
use Illuminate\Http\JsonResponse;

it('returns success true with empty data', function () {
    $response = ApiResponseHelper::say();
    expect($response)->toBeInstanceOf(JsonResponse::class);
    $data = $response->getData(true);
    expect($data['success'])->toBeTrue();
    expect($data['payload'])->toBe([]);
});

it('wraps data in payload key', function () {
    $response = ApiResponseHelper::say(['name' => 'test']);
    $data = $response->getData(true);
    expect($data['payload']['name'])->toBe('test');
});

it('uses custom status code', function () {
    $response = ApiResponseHelper::say(['ok' => true], 201);
    expect($response->getStatusCode())->toBe(201);
});

it('extracts success key from data to top level', function () {
    $response = ApiResponseHelper::say(['success' => false, 'msg' => 'fail']);
    $data = $response->getData(true);
    expect($data['success'])->toBeFalse();
    expect($data['payload']['msg'])->toBe('fail');
});

it('defaults status to 200', function () {
    $response = ApiResponseHelper::say();
    expect($response->getStatusCode())->toBe(200);
});

it('sayError sets success to false', function () {
    $response = ApiResponseHelper::sayError(['errorKey' => 'test', 'message' => 'fail']);
    $data = $response->getData(true);
    expect($data['success'])->toBeFalse();
});

it('sayError uses custom status code', function () {
    $response = ApiResponseHelper::sayError(['message' => 'gone'], 410);
    expect($response->getStatusCode())->toBe(410);
});

it('sayError includes errorKey and message in payload', function () {
    $response = ApiResponseHelper::sayError(['errorKey' => 'validation', 'message' => 'Invalid']);
    $data = $response->getData(true);
    expect($data['payload']['errorKey'])->toBe('validation');
    expect($data['payload']['message'])->toBe('Invalid');
});
