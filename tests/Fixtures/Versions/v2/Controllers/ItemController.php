<?php

declare(strict_types=1);

namespace Tests\Fixtures\Versions\v2\Controllers;

use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends \Tests\Fixtures\Versions\v1\Controllers\ItemController
{
    /**
     * Show item v2
     * Returns single item by ID with version
     *
     * @input integer $id Item ID
     *
     * @output integer $id Item ID
     * @output string $name Item name
     * @output string $version API version
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        return $this->success([
            'id' => $request->input('id'),
            'name' => 'test-v2',
            'version' => 'v2',
        ]);
    }

    /**
     * Search items
     * Search items by query
     *
     * @input string ?$query Search query
     *
     * @output array $results Search results
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        return $this->success([
            'results' => [],
            'query' => $request->input('query'),
        ]);
    }
}
