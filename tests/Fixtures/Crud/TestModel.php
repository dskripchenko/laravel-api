<?php

declare(strict_types=1);

namespace Tests\Fixtures\Crud;

use Illuminate\Database\Eloquent\Model;

class TestModel extends Model
{
    protected $table = 'test_items';

    protected $guarded = [];

    public $timestamps = false;
}
