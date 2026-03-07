<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Controllers\ApiController;
use Dskripchenko\LaravelApi\Resources\BaseJsonResource;
use Illuminate\Http\JsonResponse;

beforeEach(function () {
    $this->controller = new class extends ApiController {
        public function callSuccess($payload = [], $status = 200): JsonResponse
        {
            return $this->success($payload, $status);
        }

        public function callError($payload = [], $status = 200): JsonResponse
        {
            return $this->error($payload, $status);
        }

        public function callValidationError($messages): JsonResponse
        {
            return $this->validationError($messages);
        }

        public function callCreated($payload = []): JsonResponse
        {
            return $this->created($payload);
        }

        public function callNoContent(): JsonResponse
        {
            return $this->noContent();
        }

        public function callNotFound(string $message = 'Not found'): JsonResponse
        {
            return $this->notFound($message);
        }
    };
});

it('returns success response with payload', function () {
    $response = $this->controller->callSuccess(['key' => 'value']);
    $data = $response->getData(true);
    expect($data['success'])->toBeTrue();
    expect($data['payload']['key'])->toBe('value');
});

it('returns success with custom status code', function () {
    $response = $this->controller->callSuccess(['ok' => true], 201);
    expect($response->getStatusCode())->toBe(201);
});

it('converts JsonResource to array in success', function () {
    $resource = new BaseJsonResource(['id' => 1, 'name' => 'test']);
    $response = $this->controller->callSuccess($resource);
    $data = $response->getData(true);
    expect($data['success'])->toBeTrue();
    expect($data['payload']['id'])->toBe(1);
});

it('returns success with empty payload', function () {
    $response = $this->controller->callSuccess();
    $data = $response->getData(true);
    expect($data['success'])->toBeTrue();
    expect($data['payload'])->toBe([]);
});

it('returns error response', function () {
    $response = $this->controller->callError(['errorKey' => 'fail', 'message' => 'Error']);
    $data = $response->getData(true);
    expect($data['success'])->toBeFalse();
    expect($data['payload']['errorKey'])->toBe('fail');
});

it('converts JsonResource to array in error', function () {
    $resource = new BaseJsonResource(['error' => 'bad']);
    $response = $this->controller->callError($resource);
    $data = $response->getData(true);
    expect($data['success'])->toBeFalse();
});

it('returns validation error with errorKey and messages', function () {
    $messages = ['name' => ['Name is required']];
    $response = $this->controller->callValidationError($messages);
    $data = $response->getData(true);
    expect($data['success'])->toBeFalse();
    expect($data['payload']['errorKey'])->toBe('validation');
    expect($data['payload']['messages'])->toBe($messages);
});

it('returns created response with 201 status', function () {
    $response = $this->controller->callCreated(['id' => 1]);
    expect($response->getStatusCode())->toBe(201);
    $data = $response->getData(true);
    expect($data['success'])->toBeTrue();
    expect($data['payload']['id'])->toBe(1);
});

it('returns no content response with 204 status', function () {
    $response = $this->controller->callNoContent();
    expect($response->getStatusCode())->toBe(204);
});

it('returns not found response with 404 status', function () {
    $response = $this->controller->callNotFound('User not found');
    expect($response->getStatusCode())->toBe(404);
    $data = $response->getData(true);
    expect($data['success'])->toBeFalse();
    expect($data['payload']['errorKey'])->toBe('not_found');
    expect($data['payload']['message'])->toBe('User not found');
});

it('returns not found with default message', function () {
    $response = $this->controller->callNotFound();
    $data = $response->getData(true);
    expect($data['payload']['message'])->toBe('Not found');
});
