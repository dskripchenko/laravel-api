<?php

namespace Dskripchenko\LaravelApi\Interfaces;

use Dskripchenko\LaravelApi\Components\Meta;
use Dskripchenko\LaravelApi\Resources\BaseJsonResource;
use Dskripchenko\LaravelApi\Resources\BaseJsonResourceCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Interface CrudServiceInterface
 * @package Dskripchenko\LaravelApi\Interfaces
 */
interface CrudServiceInterface
{
    /**
     * @return Meta
     */
    public function meta(): Meta;

    /**
     * @return Builder
     */
    public function query(): Builder;

    /**
     * @param Model $model
     * @return BaseJsonResource
     */
    public function resource(Model $model): BaseJsonResource;

    /**
     * @param Collection $collection
     * @return BaseJsonResourceCollection
     */
    public function collection(Collection $collection): BaseJsonResourceCollection;

    /**
     * @param array $data
     * @return BaseJsonResourceCollection
     */
    public function search(array $data = []): BaseJsonResourceCollection;

    /**
     * @param array $data
     * @return BaseJsonResource
     */
    public function create(array $data = []): BaseJsonResource;

    /**
     * @param int $id
     * @return BaseJsonResource
     */
    public function read(int $id): BaseJsonResource;

    /**
     * @param int $id
     * @param array $data
     * @return BaseJsonResource
     */
    public function update(int $id, array $data = []): BaseJsonResource;

    /**
     * @param int $id
     * @return BaseJsonResource
     * @throws \Exception
     */
    public function delete(int $id): BaseJsonResource;
}
