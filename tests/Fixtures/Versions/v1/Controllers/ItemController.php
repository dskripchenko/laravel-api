<?php

declare(strict_types=1);

namespace Tests\Fixtures\Versions\v1\Controllers;

use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends ApiController
{
    /**
     * List items
     * Returns paginated list
     *
     * @input integer ?$page Page number
     * @input integer ?$perPage Items per page
     *
     * @output array $items Items list
     * @output integer $total Total count
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        return $this->success(['items' => [], 'total' => 0]);
    }

    /**
     * Show item
     * Returns single item by ID
     *
     * @input integer $id Item ID
     *
     * @output integer $id Item ID
     * @output string $name Item name
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        return $this->success(['id' => $request->input('id'), 'name' => 'test']);
    }

    /**
     * Create item
     * Creates a new item
     *
     * @input string $name Item name
     * @input string ?$description Item description
     *
     * @output integer $id Created item ID
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function create(Request $request): JsonResponse
    {
        return $this->success(['id' => 1, 'name' => $request->input('name')]);
    }

    /**
     * Update item
     * Updates an existing item
     *
     * @input integer $id Item ID
     * @input string $name Item name
     *
     * @output integer $id Updated item ID
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        return $this->success(['id' => $request->input('id'), 'name' => $request->input('name')]);
    }

    /**
     * Delete item
     * Deletes an item
     *
     * @input integer $id Item ID
     *
     * @output boolean $deleted Deletion status
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(Request $request): JsonResponse
    {
        return $this->success(['deleted' => true]);
    }
}
