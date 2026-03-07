<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Resources\BaseJsonResource;
use Dskripchenko\LaravelApi\Resources\BaseJsonResourceCollection;

it('resolves data from resource', function () {
    $resource = new BaseJsonResource(['id' => 1, 'name' => 'test']);
    $data = $resource->resolve();
    expect($data['id'])->toBe(1);
    expect($data['name'])->toBe('test');
});

it('calls prepareResponseData if method exists on resource', function () {
    $model = new class {
        public $id = 1;
        public $name = 'raw';

        public function toArray(): array
        {
            return ['id' => $this->id, 'name' => $this->name];
        }

        public function prepareResponseData(array $data): array
        {
            $data['name'] = 'prepared';
            return $data;
        }
    };

    $resource = new BaseJsonResource($model);
    $data = $resource->resolve();
    expect($data['name'])->toBe('prepared');
});

it('skips prepareResponseData if method does not exist', function () {
    $model = new class {
        public $id = 1;

        public function toArray(): array
        {
            return ['id' => $this->id];
        }
    };

    $resource = new BaseJsonResource($model);
    $data = $resource->resolve();
    expect($data['id'])->toBe(1);
});

it('collection returns BaseJsonResourceCollection', function () {
    $collection = BaseJsonResource::collection([]);
    expect($collection)->toBeInstanceOf(BaseJsonResourceCollection::class);
});

it('collection passes collects class', function () {
    $collection = BaseJsonResource::collection([]);
    expect($collection->collects)->toBe(BaseJsonResource::class);
});
