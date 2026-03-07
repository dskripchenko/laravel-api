<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Requests\CrudSearchRequest;

it('has filter rules', function () {
    $request = new CrudSearchRequest();
    $rules = $request->rules();
    expect($rules)->toHaveKey('filter');
    expect($rules['filter'])->toContain('nullable');
    expect($rules['filter'])->toContain('array');
});

it('requires column when filter is present', function () {
    $rules = (new CrudSearchRequest())->rules();
    expect($rules['filter.*.column'])->toContain('required_with:filter');
});

it('allows valid operators', function () {
    $rules = (new CrudSearchRequest())->rules();
    $operatorRule = $rules['filter.*.operator'];
    expect($operatorRule)->toContain('in:=,!=,>,<,>=,<=,in,not_in,like');
    expect($operatorRule)->not->toContain('rlike');
    expect($operatorRule)->not->toContain('ilike');
});

it('has perPage constraints', function () {
    $rules = (new CrudSearchRequest())->rules();
    expect($rules['perPage'])->toContain('min:1');
    expect($rules['perPage'])->toContain('max:100');
});

it('has nullable order', function () {
    $rules = (new CrudSearchRequest())->rules();
    expect($rules['order'])->toContain('nullable');
    expect($rules['order'])->toContain('array');
});

it('requires boolean for order value', function () {
    $rules = (new CrudSearchRequest())->rules();
    expect($rules['order.*.value'])->toContain('boolean');
});
