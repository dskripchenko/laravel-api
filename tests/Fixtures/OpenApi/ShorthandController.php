<?php

declare(strict_types=1);

namespace Tests\Fixtures\OpenApi;

use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShorthandController extends ApiController
{
    /**
     * Create order
     *
     * @input integer $product_id Product ID
     * @input integer $quantity Quantity
     *
     * @response 201 {OrderResponse}
     * @response 422 {OrderError}
     */
    public function create(Request $request): JsonResponse
    {
        return $this->created([]);
    }
}
