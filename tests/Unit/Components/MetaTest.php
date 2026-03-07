<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Components\Meta;

it('adds string column', function () {
    $meta = (new Meta())->string('name', 'Name');
    $arr = $meta->toArray();
    expect($arr['columns']['name']['type'])->toBe('string');
    expect($arr['columns']['name']['name'])->toBe('Name');
    expect($arr['columns']['name']['options']['required'])->toBeTrue();
});

it('adds boolean column', function () {
    $meta = (new Meta())->boolean('active', 'Active', false);
    $arr = $meta->toArray();
    expect($arr['columns']['active']['type'])->toBe('boolean');
    expect($arr['columns']['active']['options']['required'])->toBeFalse();
});

it('adds number column', function () {
    $meta = (new Meta())->number('price', 'Price');
    $arr = $meta->toArray();
    expect($arr['columns']['price']['type'])->toBe('number');
});

it('adds integer column', function () {
    $meta = (new Meta())->integer('count', 'Count');
    $arr = $meta->toArray();
    expect($arr['columns']['count']['type'])->toBe('integer');
});

it('adds hidden column', function () {
    $meta = (new Meta())->hidden('token', 'Token');
    $arr = $meta->toArray();
    expect($arr['columns']['token']['type'])->toBe('hidden');
});

it('adds select column with items', function () {
    $items = ['a' => 'Option A', 'b' => 'Option B'];
    $meta = (new Meta())->select('status', 'Status', $items);
    $arr = $meta->toArray();
    expect($arr['columns']['status']['type'])->toBe('select');
    expect($arr['columns']['status']['options']['items'])->toBe($items);
});

it('adds file column with src', function () {
    $meta = (new Meta())->file('avatar', 'Avatar', '/uploads');
    $arr = $meta->toArray();
    expect($arr['columns']['avatar']['type'])->toBe('file');
    expect($arr['columns']['avatar']['options']['src'])->toBe('/uploads');
});

it('passes extra options', function () {
    $meta = (new Meta())->string('email', 'Email', true, ['placeholder' => 'Enter email']);
    $arr = $meta->toArray();
    expect($arr['columns']['email']['options']['placeholder'])->toBe('Enter email');
});

it('adds action with boolean condition', function () {
    $meta = (new Meta())->action('create', true)->action('delete', false);
    $arr = $meta->toArray();
    expect($arr['actions']['create'])->toBeTrue();
    expect($arr['actions']['delete'])->toBeFalse();
});

it('adds action with callable condition', function () {
    $meta = (new Meta())->action('create', fn() => true);
    $arr = $meta->toArray();
    expect($arr['actions']['create'])->toBeTrue();
});

it('registers crud actions', function () {
    $meta = (new Meta())->crud();
    $arr = $meta->toArray();
    expect($arr['actions'])->toHaveKeys(['create', 'read', 'update', 'delete']);
    expect($arr['actions']['create'])->toBeTrue();
});

it('registers crud with mixed conditions', function () {
    $meta = (new Meta())->crud(true, false, fn() => true, false);
    $arr = $meta->toArray();
    expect($arr['actions']['create'])->toBeTrue();
    expect($arr['actions']['read'])->toBeFalse();
    expect($arr['actions']['update'])->toBeTrue();
    expect($arr['actions']['delete'])->toBeFalse();
});

it('filters hidden columns from swagger inputs', function () {
    $meta = (new Meta())
        ->string('name', 'Name', true)
        ->hidden('secret', 'Secret');
    $inputs = $meta->getSwaggerInputs();
    expect($inputs)->toHaveKey('name');
    expect($inputs)->not->toHaveKey('secret');
});

it('formats swagger inputs with required marker', function () {
    $meta = (new Meta())
        ->string('name', 'Name', true)
        ->string('bio', 'Bio', false);
    $inputs = $meta->getSwaggerInputs();
    expect($inputs['name'])->toContain('$name');
    expect($inputs['bio'])->toContain('?$bio');
});

it('returns empty swagger inputs for empty meta', function () {
    $meta = new Meta();
    expect($meta->getSwaggerInputs())->toBe([]);
});

it('returns column keys', function () {
    $meta = (new Meta())
        ->string('name', 'Name')
        ->integer('age', 'Age')
        ->hidden('secret', 'Secret');
    expect($meta->getColumnKeys())->toBe(['name', 'age', 'secret']);
});

it('returns empty column keys for empty meta', function () {
    $meta = new Meta();
    expect($meta->getColumnKeys())->toBe([]);
});

it('supports fluent chaining', function () {
    $meta = (new Meta())
        ->string('a', 'A')
        ->integer('b', 'B')
        ->crud();
    expect($meta)->toBeInstanceOf(Meta::class);
    $arr = $meta->toArray();
    expect($arr['columns'])->toHaveCount(2);
    expect($arr['actions'])->toHaveCount(4);
});
