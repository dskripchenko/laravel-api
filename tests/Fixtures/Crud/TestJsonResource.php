<?php

declare(strict_types=1);

namespace Tests\Fixtures\Crud;

use Dskripchenko\LaravelApi\Resources\BaseJsonResource;

class TestJsonResource extends BaseJsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
        ];
    }
}
