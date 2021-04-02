<?php

namespace Dskripchenko\LaravelApi\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

/**
 * Class BaseJsonResource
 * @package Dskripchenko\LaravelApi\Resources
 */
class BaseJsonResource extends JsonResource
{
    /**
     * @param Request|null $request
     * @return array
     */
    public function resolve($request = null): array
    {
        $data = $this->toArray($request ?? request());
        if ($data instanceof Arrayable) {
            $data = $data->toArray();
        } elseif ($data instanceof JsonSerializable) {
            $data = $data->jsonSerialize();
        }

        if (method_exists($this->resource, 'prepareResponseData')) {
            $data = $this->resource->prepareResponseData($data);
        }

        return $this->filter((array) $data);
    }

    /**
     * Create new anonymous resource collection.
     *
     * @param  mixed  $resource
     * @return BaseJsonResourceCollection
     */
    public static function collection($resource): BaseJsonResourceCollection
    {
        $collection = new BaseJsonResourceCollection($resource, static::class);

        return tap($collection, function ($collection) {
            if (property_exists(static::class, 'preserveKeys')) {
                $collection->preserveKeys = (new static([]))->preserveKeys === true;
            }
        });
    }
}
