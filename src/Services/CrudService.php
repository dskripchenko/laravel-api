<?php

namespace Dskripchenko\LaravelApi\Services;

use Dskripchenko\LaravelApi\Components\Meta;
use Dskripchenko\LaravelApi\Interfaces\CrudServiceInterface;
use Dskripchenko\LaravelApi\Resources\BaseJsonResource;
use Dskripchenko\LaravelApi\Resources\BaseJsonResourceCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Class CrudService
 * @package Dskripchenko\LaravelApi\Services
 */
abstract class CrudService implements CrudServiceInterface
{
    /**
     * @return Meta
     */
    abstract public function meta(): Meta;

    /**
     * @return Builder
     */
    abstract public function query(): Builder;

    /**
     * @param Model $model
     * @return BaseJsonResource
     */
    abstract public function resource(Model $model): BaseJsonResource;

    /**
     * @param Collection $collection
     * @return BaseJsonResourceCollection
     */
    abstract public function collection(Collection $collection): BaseJsonResourceCollection;

    /**
     * @param array $data
     * @return BaseJsonResourceCollection
     */
    public function search(array $data = []): BaseJsonResourceCollection
    {
        $query = $this->query();
        foreach (Arr::get($data, 'filter', []) as $filter) {
            $column   = Arr::get($filter, 'column');
            $operator = Arr::get($filter, 'operator', '=');
            $value    = Arr::get($filter, 'value');

            if ($operator === 'in') {
                if (!is_array($value)) {
                    $value = explode(',', $value);
                }
                $query->whereIn($column, $value);
                continue;
            }

            if ($operator === 'not_in') {
                if (!is_array($value)) {
                    $value = explode(',', $value);
                }
                $query->whereNotIn($column, $value);
                continue;
            }

            $query->where($column, $operator, $value);
        }

        foreach (Arr::get($data, 'order', []) as $order) {
            $column = Arr::get($order, 'column');
            $value  = Arr::get($order, 'value');

            $query->orderBy($column, $value ? 'asc' : 'desc');
        }

        $page      = Arr::get($data, 'page', 1);
        $perPage   = Arr::get($data, 'perPage', 10);
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $this
            ->collection(Collection::make($paginator->items()))
            ->additional([
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage()
            ]);
    }

    /**
     * @param array $data
     * @return BaseJsonResource
     */
    public function create(array $data = []): BaseJsonResource
    {
        $model = $this->query()->create($data);
        return $this->resource($model);
    }

    /**
     * @param int $id
     * @return BaseJsonResource
     */
    public function read(int $id): BaseJsonResource
    {
        $model = $this->query()->findOrFail($id);
        return $this->resource($model);
    }

    /**
     * @param int $id
     * @param array $data
     * @return BaseJsonResource
     */
    public function update(int $id, array $data = []): BaseJsonResource
    {
        $model = $this->query()->findOrFail($id);
        $model->fill($data);
        $model->save();
        return $this->resource($model);
    }

    /**
     * @param int $id
     * @return BaseJsonResource
     * @throws \Exception
     */
    public function delete(int $id): BaseJsonResource
    {
        $model = $this->query()->findOrFail($id);
        $model->delete();
        return $this->resource($model);
    }
}
