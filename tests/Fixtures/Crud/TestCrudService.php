<?php

declare(strict_types=1);

namespace Tests\Fixtures\Crud;

use Dskripchenko\LaravelApi\Components\Meta;
use Dskripchenko\LaravelApi\Resources\BaseJsonResource;
use Dskripchenko\LaravelApi\Resources\BaseJsonResourceCollection;
use Dskripchenko\LaravelApi\Services\CrudService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class TestCrudService extends CrudService
{
    public function meta(): Meta
    {
        return (new Meta())
            ->string('name', 'Name', true)
            ->string('description', 'Description', false)
            ->hidden('secret', 'Secret', false)
            ->crud();
    }

    public function query(): Builder
    {
        return TestModel::query();
    }

    public function resource(Model $model): BaseJsonResource
    {
        return new TestJsonResource($model);
    }

    public function collection(Collection $collection): BaseJsonResourceCollection
    {
        return TestJsonResource::collection($collection);
    }
}
