<?php

declare(strict_types=1);

namespace Tests\Fixtures\Swagger;

use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends ApiController
{
    /**
     * Get user
     * Returns user by ID
     *
     * @input integer $id User ID
     *
     * @output {UserResponse}
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getUser(Request $request): JsonResponse
    {
        return $this->success(['id' => 1, 'name' => 'Test', 'email' => 'test@test.com']);
    }

    /**
     * Create user
     * Creates a new user
     *
     * @input string $name User name
     * @input string $email User email
     *
     * @output integer $id Created user ID
     * @output string $name User name
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createUser(Request $request): JsonResponse
    {
        return $this->success(['id' => 1, 'name' => $request->input('name')]);
    }
}
