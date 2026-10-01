<?php

declare(strict_types=1);

namespace Tests\Fixtures\OpenApi;

use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class NestedController extends ApiController
{
    /**
     * Save a layout
     *
     * @input string $key Layout key
     * @input string $widgets[].slug Widget slug
     * @input integer ?$widgets[].size Columns
     * @input array $widgets Widgets, declared after their fields
     * @input array ?$abilities Abilities
     * @input string $abilities[] One ability
     */
    public function save(): JsonResponse
    {
        return $this->success();
    }

    /**
     * Show a record
     *
     * @output integer $id Identifier
     * @output string ?$note Note
     * @output object $owner Owner
     * @output string $owner.name Owner name
     * @output string ?$owner.email Owner email
     */
    public function show(): JsonResponse
    {
        return $this->success();
    }
}
