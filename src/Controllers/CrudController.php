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
    public function getOpenApiMetaInputs(): array
    {
        return $this->crudService->meta()->getOpenApiInputs();
    }

    /**
     * Get the meta information
     * @return JsonResponse
     */
    public function meta(): JsonResponse
    {
        return $this->success($this->crudService->meta()->toArray());
    }

    /**
     * Get the list of rows
     *
     * @param CrudSearchRequest $request
     * @return JsonResponse
     */
    public function search(CrudSearchRequest $request): JsonResponse
    {
        return $this->success($this->crudService->search($request->all()));
    }

    /**
     * Create a row
     * @input [getOpenApiMetaInputs]
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function create(Request $request): JsonResponse
    {
        return $this->success($this->crudService->create($request->all()));
    }

    /**
     * Get a row
     *
     * @input integer $id The row's identifier
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
     * Update a row
     *
     * @input integer $id The row's identifier
     * @input [getOpenApiMetaInputs]
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
     * Delete a row
     *
     * @input integer $id The row's identifier
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
