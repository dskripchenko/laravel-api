<?php

namespace Dskripchenko\LaravelApi\Components;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Class Meta
 * @package Dskripchenko\LaravelApi\Components
 */
class Meta
{
    protected $columns = [];

    protected $actions = [];

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'columns' => $this->columns,
            'actions' => $this->actions,
        ];
    }

    /**
     * @param string $action
     * @param bool|callable $condition
     * @return $this
     */
    public function action(string $action, $condition = true): Meta
    {
        if (is_callable($condition)) {
            $condition = $condition();
        }

        $this->actions[$action] = $condition;

        return $this;
    }

    /**
     * @param bool|callable $condition
     * @return $this
     */
    public function create($condition = true): Meta
    {
        return $this->action('create', $condition);
    }

    /**
     * @param bool|callable $condition
     * @return $this
     */
    public function read($condition = true): Meta
    {
        return $this->action('read', $condition);
    }

    /**
     * @param bool|callable $condition
     * @return $this
     */
    public function update($condition = true): Meta
    {
        return $this->action('update', $condition);
    }

    /**
     * @param bool|callable $condition
     * @return $this
     */
    public function delete($condition = true): Meta
    {
        return $this->action('delete', $condition);
    }

    /**
     * @param bool|callable $create
     * @param bool|callable $read
     * @param bool|callable $update
     * @param bool|callable $delete
     * @return $this
     */
    public function crud($create = true, $read = true, $update = true, $delete = true): Meta
    {
        return $this->create($create)
            ->read($read)
            ->update($update)
            ->delete($delete);
    }


    /**
     * @param string $type
     * @param string $key
     * @param string $name
     * @param array $options
     * @return $this
     */
    public function column(string $type, string $key, string $name, array $options = []): Meta
    {
        $this->columns[$key] = [
            'type' => $type,
            'name' => $name,
            'options' => $options,
        ];
        return $this;
    }

    /**
     * @return array
     */
    public function getSwaggerInputs(): array
    {
        return Collection::make($this->columns)
            ->filter(function ($value) {
                return Arr::get($value, 'type') !== 'hidden';
            })
            ->mapWithKeys(function ($value, $key) {
                $required = Arr::get($value, 'options.required', false) ? '$' : '?$';
                return [$key => "{$value['type']} {$required}{$key} {$value['name']}"];
            })
            ->toArray();
    }

    /**
     * @param string $key
     * @param string $name
     * @param bool $required
     * @param array $options
     * @return $this
     */
    public function string(string $key, string $name, bool $required = true, array $options = []): Meta
    {
        $options['required'] = $required;
        return $this->column('string', $key, $name, $options);
    }

    /**
     * @param string $key
     * @param string $name
     * @param bool $required
     * @param array $options
     * @return $this
     */
    public function boolean(string $key, string $name, bool $required = true, array $options = []): Meta
    {
        $options['required'] = $required;
        return $this->column('boolean', $key, $name, $options);
    }

    /**
     * @param string $key
     * @param string $name
     * @param bool $required
     * @param array $options
     * @return $this
     */
    public function number(string $key, string $name, $required = true, array $options = []): Meta
    {
        $options['required'] = $required;
        return $this->column('number', $key, $name, $options);
    }

    /**
     * @param string $key
     * @param string $name
     * @param bool $required
     * @param array $options
     * @return $this
     */
    public function integer(string $key, string $name, $required = true, array $options = []): Meta
    {
        $options['required'] = $required;
        return $this->column('integer', $key, $name, $options);
    }

    /**
     * @param string $key
     * @param string $name
     * @param bool $required
     * @param array $options
     * @return $this
     */
    public function hidden(string $key, string $name, $required = true, array $options = []): Meta
    {
        $options['required'] = $required;
        return $this->column('hidden', $key, $name, $options);
    }

    /**
     * @param string $key
     * @param string $name
     * @param array $items
     * @param bool $required
     * @param array $options
     * @return $this
     */
    public function select(string $key, string $name, array $items = [], $required = true, array $options = []): Meta
    {
        $options['required'] = $required;
        $options['items']    = $items;
        return $this->column('select', $key, $name, $options);
    }

    /**
     * @param string $key
     * @param string $name
     * @param string $src
     * @param bool $required
     * @param array $options
     * @return $this
     */
    public function file(string $key, string $name, string $src, $required = true, array $options = []): Meta
    {
        $options['required'] = $required;
        $options['src']      = $src;
        return $this->column('file', $key, $name, $options);
    }
}
