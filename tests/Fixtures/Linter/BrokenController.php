<?php

declare(strict_types=1);

namespace Tests\Fixtures\Linter;

use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

/**
 * A controller wrong in every way the linter is meant to notice.
 *
 * Each defect here is one the application survives: it boots, it serves, and
 * the mistake reaches whoever consumes the API. That is the whole reason for
 * the fixture — a linter tested only against valid markup proves nothing.
 */
class BrokenController extends ApiController
{
    /**
     * Everything correct
     *
     * @input string $name Name
     * @input object $address Address
     * @input string $address.city City
     * @output integer $id Identifier
     *
     * @response 200 {KnownTemplate}
     *
     * @security KnownScheme
     *
     * @default $name anonymous
     */
    public function fine(): JsonResponse
    {
        return $this->success();
    }

    /**
     * Unknown type, silently coerced to string
     *
     * @input datetime $when When
     */
    public function unknownType(): JsonResponse
    {
        return $this->success();
    }

    /**
     * Nesting whose parent was never declared
     *
     * @input string $address.city City
     */
    public function orphanNesting(): JsonResponse
    {
        return $this->success();
    }

    /**
     * A parent declared as the wrong kind of container
     *
     * @input string $tags Tags
     * @input integer $tags[].id Tag id
     */
    public function nestingTypeMismatch(): JsonResponse
    {
        return $this->success();
    }

    /**
     * References that point at nothing
     *
     * @input @MissingRequest
     * @output @MissingModel[] $items Items
     *
     * @response 404 {MissingTemplate}
     *
     * @security MissingScheme
     */
    public function danglingRefs(): JsonResponse
    {
        return $this->success();
    }

    /**
     * Markup that does not parse at all
     *
     * @input string missingDollar Description
     * @response nope Something
     * @default noDollar 1
     */
    public function malformed(): JsonResponse
    {
        return $this->success();
    }

    /**
     * The same field twice, and a default for a field that does not exist
     *
     * @input string $title Title
     * @input string $title Title again
     *
     * @default $absent 1
     * @example $absent 2
     */
    public function duplicatesAndStrays(): JsonResponse
    {
        return $this->success();
    }

    /**
     * Inputs from a method that is not there
     *
     * @input [noSuchMethod]
     */
    public function callableMissing(): JsonResponse
    {
        return $this->success();
    }

    /**
     * Two answers for one status code
     *
     * @response 200 {KnownTemplate}
     * @response 200 Something else
     * @response 999 Impossible
     */
    public function duplicateResponses(): JsonResponse
    {
        return $this->success();
    }

    /**
     * Public, and nothing routes to it.
     */
    public function neverRouted(): JsonResponse
    {
        return $this->success();
    }

    /**
     * Cannot serve an action: not public.
     */
    protected function hidden(): JsonResponse
    {
        return $this->success();
    }
}
