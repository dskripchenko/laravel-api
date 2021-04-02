<?php

namespace Dskripchenko\LaravelApi\Controllers;

use Dskripchenko\LaravelApi\Interfaces\CrudServiceInterface;
use Dskripchenko\LaravelApi\Requests\CrudSearchRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class CrudController
 * @package Dskripchenko\LaravelApi\Controllers
 */
class CrudController extends ApiController
{
    /**
     * @var CrudServiceInterface
     */
    protected $crudService;

    /**
     * CrudController constructor.
     * @param CrudServiceInterface $crudService
     */
    public function __construct(CrudServiceInterface $crudService)
    {
        $this->crudService = $crudService;
    }

    /**
     * @return array
     */
    public function getSwaggerMetaInputs(): array
    {
        return $this->crudService->meta()->getSwaggerInputs();
    }

    /**
     * Получить мета информацию
     * @return JsonResponse
     */
    public function meta(): JsonResponse
    {
        return $this->success($this->crudService->meta()->toArray());
    }

    /**
     * Получить список записей
     *
     * @param CrudSearchRequest $request
     * @return JsonResponse
     */
    public function search(CrudSearchRequest $request): JsonResponse
    {
        return $this->success($this->crudService->search($request->all()));
    }

    /**
     * Создать запись
     * @input [getSwaggerMetaInputs]
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function create(Request $request): JsonResponse
    {
        return $this->success($this->crudService->create($request->all()));
    }

    /**
     * Получить запись
     *
     * @input integer $id Идентификатор записи
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function read(Request $request, int $id): JsonResponse
    {
        return $this->success($this->crudService->read($id));
    }

    /**
     * Обновить запись
     *
     * @input integer $id Идентификатор записи
     * @input [getSwaggerMetaInputs]
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        return $this->success($this->crudService->update($id, $request->all()));
    }

    /**
     * Удалить запись
     *
     * @input integer $id Идентификатор записи
     *
     * @param int $id
     * @return JsonResponse
     * @throws \Exception
     */
    public function delete(int $id): JsonResponse
    {
        return $this->success($this->crudService->delete($id));
    }
}
