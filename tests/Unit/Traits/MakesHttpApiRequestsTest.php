<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Traits\Testing\MakesHttpApiRequests;

it('trait provides api method', function () {
    expect(method_exists(MakesHttpApiRequests::class, 'api'))->toBeTrue();
});

it('trait provides call method', function () {
    expect(method_exists(MakesHttpApiRequests::class, 'call'))->toBeTrue();
});

it('trait provides createTestRequest method', function () {
    $ref = new \ReflectionMethod(MakesHttpApiRequests::class, 'createTestRequest');
    expect($ref->isProtected())->toBeTrue();
});

it('api method has correct parameter signature', function () {
    $ref = new \ReflectionMethod(MakesHttpApiRequests::class, 'api');
    $params = $ref->getParameters();
    $names = array_map(fn($p) => $p->getName(), $params);
    expect($names)->toBe(['version', 'controller', 'action', 'data', 'headers']);
});
