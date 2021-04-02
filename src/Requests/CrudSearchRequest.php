<?php

namespace Dskripchenko\LaravelApi\Requests;

/**
 * Class CrudSearchRequest
 * @package Dskripchenko\LaravelApi\Requests
 */
class CrudSearchRequest extends BaseApiRequest
{
    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            'filter' => 'nullable|array',
            'filter.*.column' => 'required_with:filter|string',
            'filter.*.operator' => 'nullable|string|in:=,!=,>,<,>=,<=,in,not_in,like,ilike,rlike',
            'filter.*.value' => 'required_with:filter',

            'order' => 'nullable|array',
            'order.*.column' => 'required_with:order|string',
            'order.*.value' => 'required_with:order|boolean',

            'page' => 'nullable|integer',
            'perPage' => 'nullable|integer|min:1|max:100'
        ];
    }
}
