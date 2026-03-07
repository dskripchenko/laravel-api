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
use Illuminate\Support\Facades\DB;

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
     * @var array|null
     */
    private ?array $cachedAllowedColumns = null;

    /**
     * @return array
     */
    protected function getAllowedColumns(): array
    {
        if ($this->cachedAllowedColumns === null) {
            $this->cachedAllowedColumns = $this->meta()->getColumnKeys();
        }

        return $this->cachedAllowedColumns;
    }

    /**
     * @param string|null $column
     * @return bool
     */
    protected function isAllowedColumn(?string $column): bool
    {
        if ($column === null) {
            return false;
        }

        $allowed = $this->getAllowedColumns();
        if (empty($allowed)) {
            return false;
        }

        return in_array($column, $allowed, true);
    }

    /**
     * @param array $data
     * @return array
     */
    protected function filterDataByAllowedColumns(array $data): array
    {
        $allowed = $this->getAllowedColumns();
        if (empty($allowed)) {
            return $data;
        }

        return Arr::only($data, $allowed);
    }

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

            if (!$this->isAllowedColumn($column)) {
                continue;
            }

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

            if ($operator === 'between') {
                if (is_array($value) && count($value) === 2) {
                    $query->whereBetween($column, $value);
                }
                continue;
            }

            if ($operator === 'is_null') {
                $query->whereNull($column);
                continue;
            }

            if ($operator === 'is_not_null') {
                $query->whereNotNull($column);
                continue;
            }

            if ($operator === 'like') {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $value);
                $query->whereRaw(
                    "\"$column\" LIKE ? ESCAPE '\\'",
                    ['%' . $escaped . '%']
                );
                continue;
            }

            $query->where($column, $operator, $value);
        }

        foreach (Arr::get($data, 'order', []) as $order) {
            $column = Arr::get($order, 'column');
            $value  = Arr::get($order, 'value');

            if (!$this->isAllowedColumn($column)) {
                continue;
            }

            $direction = ($value === 'desc') ? 'desc' : 'asc';
            $query->orderBy($column, $direction);
        }

        $page      = Arr::get($data, 'page', 1);
        $perPage   = Arr::get($data, 'perPage', 10);
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $this
            ->collection(new Collection($paginator->items()))
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
        return DB::transaction(function () use ($data) {
            $model = $this->query()->create($this->filterDataByAllowedColumns($data));
            return $this->resource($model);
        });
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
        return DB::transaction(function () use ($id, $data) {
            $model = $this->query()->findOrFail($id);
            $model->fill($this->filterDataByAllowedColumns($data));
            $model->save();
            return $this->resource($model);
        });
    }

    /**
     * @param int $id
     * @return BaseJsonResource
     * @throws \Exception
     */
    public function delete(int $id): BaseJsonResource
    {
        return DB::transaction(function () use ($id) {
            $model = $this->query()->findOrFail($id);
            $model->delete();
            return $this->resource($model);
        });
    }

    /**
     * @param int $id
     * @return BaseJsonResource
     */
    public function restore(int $id): BaseJsonResource
    {
        return DB::transaction(function () use ($id) {
            $model = $this->query()->withTrashed()->findOrFail($id);
            $model->restore();
            return $this->resource($model);
        });
    }

    /**
     * @param int $id
     * @return BaseJsonResource
     */
    public function forceDelete(int $id): BaseJsonResource
    {
        return DB::transaction(function () use ($id) {
            $model = $this->query()->withTrashed()->findOrFail($id);
            $model->forceDelete();
            return $this->resource($model);
        });
    }
}
