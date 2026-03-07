<?php

declare(strict_types=1);

namespace Tests\Fixtures\Swagger;

use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExtendedController extends ApiController
{
    /**
     * Header action
     * Action with header parameters
     *
     * @header string $Authorization Bearer token
     * @header string ?$X-Request-Id Request ID
     *
     * @input string $name User name
     *
     * @output string $result Result
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function headerAction(Request $request): JsonResponse
    {
        return $this->success(['result' => 'ok']);
    }

    /**
     * Security action
     * Action with security
     *
     * @security BearerAuth
     *
     * @input string $data Some data
     *
     * @output string $result Result
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function securityAction(Request $request): JsonResponse
    {
        return $this->success(['result' => 'ok']);
    }

    /**
     * Deprecated action
     * Use newAction instead
     *
     * @deprecated Use newAction
     *
     * @input string $old Old param
     *
     * @output string $result Result
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function deprecatedAction(Request $request): JsonResponse
    {
        return $this->success(['result' => 'ok']);
    }

    /**
     * Multi response action
     * Action with multiple responses
     *
     * @input integer $id User ID
     *
     * @response 200 {UserResponse}
     * @response 422 {ValidationError}
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function multiResponseAction(Request $request): JsonResponse
    {
        return $this->success(['id' => 1]);
    }

    /**
     * Nested input action
     * Action with nested inputs
     *
     * @input object $address Address object
     * @input string $address.city City name
     * @input string $address.street Street name
     * @input array $tags Tags array
     * @input integer $tags[].id Tag ID
     * @input string $tags[].name Tag name
     *
     * @output object $address Address
     * @output string $address.city City
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function nestedInputAction(Request $request): JsonResponse
    {
        return $this->success(['address' => ['city' => 'Moscow']]);
    }

    /**
     * Format action
     * Action with format specifiers
     *
     * @input string(email) $email Email address
     * @input integer(int64) $bigId Big ID
     *
     * @output string(date-time) $createdAt Created at
     * @output integer(int32) $count Count
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function formatAction(Request $request): JsonResponse
    {
        return $this->success(['createdAt' => now(), 'count' => 1]);
    }

    /**
     * Enum action
     * Action with enum values
     *
     * @input string $status Status [active,blocked,pending]
     * @input string ?$role Role [admin,user,moderator]
     *
     * @output string $status Current status [active,blocked,pending]
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function enumAction(Request $request): JsonResponse
    {
        return $this->success(['status' => 'active']);
    }

    /**
     * Model ref action
     * Action with model references
     *
     * @input @OrderCreateRequest
     *
     * @output @User $user User object
     * @output @User[] $users Users list
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function modelRefAction(Request $request): JsonResponse
    {
        return $this->success(['user' => [], 'users' => []]);
    }

    /**
     * Default example action
     * Action with defaults and examples
     *
     * @input integer ?$page Page number
     * @input integer ?$perPage Items per page
     *
     * @default $page 1
     * @default $perPage 20
     * @example $page 3
     * @example $perPage 50
     *
     * @output array $items Items
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function defaultExampleAction(Request $request): JsonResponse
    {
        return $this->success(['items' => []]);
    }

    /**
     * File upload action
     * Action with file upload
     *
     * @input file $avatar User avatar
     * @input string $name User name
     *
     * @output string $url File URL
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function fileUploadAction(Request $request): JsonResponse
    {
        return $this->success(['url' => '/uploads/avatar.jpg']);
    }
}
