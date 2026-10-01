<?php

declare(strict_types=1);

namespace Tests\Fixtures\Linter;

use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UndeclaredFormRequest extends FormRequest
{
    /** @return array<string, string> */
    public function rules(): array
    {
        return ['name' => 'required'];
    }
}

class UndeclaredInputController extends ApiController
{
    /**
     * Validates a literal, declares nothing
     */
    public function validatesLiteral(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required']);

        return $this->success();
    }

    /**
     * Validates rules assembled at runtime, declares nothing
     */
    public function validatesRuntime(Request $request): JsonResponse
    {
        $request->validate($this->rules());

        return $this->success();
    }

    /**
     * Builds a validator through the facade
     */
    public function validatorFacade(Request $request): JsonResponse
    {
        Validator::make($request->all(), ['name' => 'required'])->validate();

        return $this->success();
    }

    /**
     * Builds a validator through the helper
     */
    public function validatorHelper(Request $request): JsonResponse
    {
        \validator($request->all(), ['name' => 'required'])->validate();

        return $this->success();
    }

    /**
     * Validates through a form request
     */
    public function formRequest(UndeclaredFormRequest $request): JsonResponse
    {
        return $this->success();
    }

    /**
     * Validates and says so
     *
     * @input string $name Name
     */
    public function declared(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required']);

        return $this->success();
    }

    /**
     * Validates and describes the fields at runtime
     *
     * @input [dynamicFields]
     */
    public function declaredDynamically(Request $request): JsonResponse
    {
        $request->validate($this->rules());

        return $this->success();
    }

    /**
     * Takes nothing
     */
    public function takesNothing(): JsonResponse
    {
        return $this->success();
    }

    /**
     * Takes nothing either
     */
    public function onlyMentionsInComment(): JsonResponse
    {
        // Nothing here calls $request->validate([...]) — it only says so.
        return $this->success();
    }

    /**
     * @return string[]
     */
    public function dynamicFields(): array
    {
        return ['string $name Name'];
    }

    /**
     * @return array<string, string>
     */
    private function rules(): array
    {
        return ['name' => 'required'];
    }
}
