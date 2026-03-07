<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Resources\BaseJsonResource;
use Dskripchenko\LaravelApi\Resources\BaseJsonResourceCollection;
use Illuminate\Http\Resources\Json\ResourceCollection;

it('sets collects property in constructor', function () {
    $collection = new BaseJsonResourceCollection([], BaseJsonResource::class);
    expect($collection->collects)->toBe(BaseJsonResource::class);
});

it('extends ResourceCollection', function () {
    $collection = new BaseJsonResourceCollection([], BaseJsonResource::class);
    expect($collection)->toBeInstanceOf(ResourceCollection::class);
});

it('wraps items correctly', function () {
    $items = [
        ['id' => 1, 'name' => 'A'],
        ['id' => 2, 'name' => 'B'],
    ];
    $collection = new BaseJsonResourceCollection($items, BaseJsonResource::class);
    $resolved = $collection->resolve(request());
    expect($resolved)->toHaveCount(2);
});
