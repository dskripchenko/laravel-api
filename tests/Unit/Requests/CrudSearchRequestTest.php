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
    expect($operatorRule)->toContain('in:=,!=,>,<,>=,<=,in,not_in,like,between,is_null,is_not_null');
    expect($operatorRule)->not->toContain('rlike');
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

it('requires string asc/desc for order value', function () {
    $rules = (new CrudSearchRequest())->rules();
    expect($rules['order.*.value'])->toContain('string');
    expect($rules['order.*.value'])->toContain('in:asc,desc');
});

it('limits filter count to 50', function () {
    $rules = (new CrudSearchRequest())->rules();
    expect($rules['filter'])->toContain('max:50');
});
