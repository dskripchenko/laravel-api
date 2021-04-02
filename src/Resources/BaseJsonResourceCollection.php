<?php

namespace Dskripchenko\LaravelApi\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Class BaseJsonResourceCollection
 * @package Dskripchenko\LaravelApi\Resources
 */
class BaseJsonResourceCollection extends ResourceCollection
{
    /**
     * @var static $collects
     */
    public $collects;

    /**
     * BaseJsonResourceCollection constructor.
     * @param $resource
     * @param $collects
     */
    public function __construct($resource, $collects)
    {
        $this->collects = $collects;

        parent::__construct($resource);
    }
}
