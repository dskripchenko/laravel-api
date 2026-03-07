<?php

declare(strict_types=1);

use Tests\Fixtures\Crud\TestCrudService;
use Tests\Fixtures\Crud\TestModel;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(function () {
    // Run migration
    $migration = include __DIR__ . '/../../Fixtures/Crud/create_test_items_table.php';
    $migration->up();

    $this->service = new TestCrudService();
});

afterEach(function () {
    $migration = include __DIR__ . '/../../Fixtures/Crud/create_test_items_table.php';
    $migration->down();
});

it('searches with empty data using defaults', function () {
    TestModel::create(['name' => 'Item 1', 'description' => 'Desc 1']);
    TestModel::create(['name' => 'Item 2', 'description' => 'Desc 2']);

    $result = $this->service->search();
    $resolved = $result->resolve(request());
    expect($resolved)->toHaveCount(2);
});

it('filters with equals operator', function () {
    TestModel::create(['name' => 'Alpha', 'description' => 'A']);
    TestModel::create(['name' => 'Beta', 'description' => 'B']);

    $result = $this->service->search([
        'filter' => [
            ['column' => 'name', 'operator' => '=', 'value' => 'Alpha'],
        ],
    ]);
    $resolved = $result->resolve(request());
    expect($resolved)->toHaveCount(1);
    expect($resolved[0]['name'])->toBe('Alpha');
});

it('filters with in operator using array', function () {
    TestModel::create(['name' => 'A']);
    TestModel::create(['name' => 'B']);
    TestModel::create(['name' => 'C']);

    $result = $this->service->search([
        'filter' => [
            ['column' => 'name', 'operator' => 'in', 'value' => ['A', 'C']],
        ],
    ]);
    $resolved = $result->resolve(request());
    expect($resolved)->toHaveCount(2);
});

it('filters with in operator using comma string', function () {
    TestModel::create(['name' => 'A']);
    TestModel::create(['name' => 'B']);
    TestModel::create(['name' => 'C']);

    $result = $this->service->search([
        'filter' => [
            ['column' => 'name', 'operator' => 'in', 'value' => 'A,C'],
        ],
    ]);
    $resolved = $result->resolve(request());
    expect($resolved)->toHaveCount(2);
});

it('filters with not_in operator', function () {
    TestModel::create(['name' => 'A']);
    TestModel::create(['name' => 'B']);
    TestModel::create(['name' => 'C']);

    $result = $this->service->search([
        'filter' => [
            ['column' => 'name', 'operator' => 'not_in', 'value' => ['B']],
        ],
    ]);
    $resolved = $result->resolve(request());
    expect($resolved)->toHaveCount(2);
});

it('filters with like operator', function () {
    TestModel::create(['name' => 'Apple']);
    TestModel::create(['name' => 'Banana']);

    $result = $this->service->search([
        'filter' => [
            ['column' => 'name', 'operator' => 'like', 'value' => 'App'],
        ],
    ]);
    $resolved = $result->resolve(request());
    expect($resolved)->toHaveCount(1);
    expect($resolved[0]['name'])->toBe('Apple');
});

it('escapes percent and underscore in like operator', function () {
    TestModel::create(['name' => '100% done']);
    TestModel::create(['name' => '100 items']);

    $result = $this->service->search([
        'filter' => [
            ['column' => 'name', 'operator' => 'like', 'value' => '100%'],
        ],
    ]);
    $resolved = $result->resolve(request());
    expect($resolved)->toHaveCount(1);
    expect($resolved[0]['name'])->toBe('100% done');
});

it('filters with between operator', function () {
    TestModel::create(['name' => 'A']);
    TestModel::create(['name' => 'M']);
    TestModel::create(['name' => 'Z']);

    $result = $this->service->search([
        'filter' => [
            ['column' => 'name', 'operator' => 'between', 'value' => ['A', 'N']],
        ],
    ]);
    $resolved = $result->resolve(request());
    expect($resolved)->toHaveCount(2);
});

it('filters with is_null operator', function () {
    TestModel::create(['name' => 'A', 'description' => null]);
    TestModel::create(['name' => 'B', 'description' => 'has desc']);

    $result = $this->service->search([
        'filter' => [
            ['column' => 'description', 'operator' => 'is_null', 'value' => null],
        ],
    ]);
    $resolved = $result->resolve(request());
    expect($resolved)->toHaveCount(1);
    expect($resolved[0]['name'])->toBe('A');
});

it('filters with is_not_null operator', function () {
    TestModel::create(['name' => 'A', 'description' => null]);
    TestModel::create(['name' => 'B', 'description' => 'has desc']);

    $result = $this->service->search([
        'filter' => [
            ['column' => 'description', 'operator' => 'is_not_null', 'value' => null],
        ],
    ]);
    $resolved = $result->resolve(request());
    expect($resolved)->toHaveCount(1);
    expect($resolved[0]['name'])->toBe('B');
});

it('orders ascending', function () {
    TestModel::create(['name' => 'Banana']);
    TestModel::create(['name' => 'Apple']);

    $result = $this->service->search([
        'order' => [
            ['column' => 'name', 'value' => 'asc'],
        ],
    ]);
    $resolved = $result->resolve(request());
    expect($resolved[0]['name'])->toBe('Apple');
    expect($resolved[1]['name'])->toBe('Banana');
});

it('orders descending', function () {
    TestModel::create(['name' => 'Apple']);
    TestModel::create(['name' => 'Banana']);

    $result = $this->service->search([
        'order' => [
            ['column' => 'name', 'value' => 'desc'],
        ],
    ]);
    $resolved = $result->resolve(request());
    expect($resolved[0]['name'])->toBe('Banana');
    expect($resolved[1]['name'])->toBe('Apple');
});

it('paginates with page and perPage', function () {
    for ($i = 1; $i <= 15; $i++) {
        TestModel::create(['name' => "Item {$i}"]);
    }

    $result = $this->service->search(['page' => 2, 'perPage' => 5]);
    $resolved = $result->resolve(request());
    expect($resolved)->toHaveCount(5);

    $additional = $result->additional;
    expect($additional['page'])->toBe(2);
    expect($additional['perPage'])->toBe(5);
    expect($additional['total'])->toBe(15);
    expect($additional['lastPage'])->toBe(3);
});

it('creates a record and returns resource', function () {
    $result = $this->service->create(['name' => 'New Item', 'description' => 'Desc']);
    $data = $result->resolve(request());
    expect($data['name'])->toBe('New Item');
    expect($data['description'])->toBe('Desc');
    expect(TestModel::count())->toBe(1);
});

it('reads an existing record', function () {
    $model = TestModel::create(['name' => 'Existing']);
    $result = $this->service->read($model->id);
    $data = $result->resolve(request());
    expect($data['name'])->toBe('Existing');
});

it('throws ModelNotFoundException for non-existent read', function () {
    $this->service->read(999);
})->throws(ModelNotFoundException::class);

it('updates a record', function () {
    $model = TestModel::create(['name' => 'Old', 'description' => 'old']);
    $result = $this->service->update($model->id, ['name' => 'New']);
    $data = $result->resolve(request());
    expect($data['name'])->toBe('New');
});

it('deletes a record and returns resource of deleted model', function () {
    $model = TestModel::create(['name' => 'ToDelete']);
    $result = $this->service->delete($model->id);
    $data = $result->resolve(request());
    expect($data['name'])->toBe('ToDelete');
    expect(TestModel::count())->toBe(0);
});

// Security tests

it('ignores filter on disallowed column name', function () {
    TestModel::create(['name' => 'A']);
    TestModel::create(['name' => 'B']);

    $result = $this->service->search([
        'filter' => [
            ['column' => 'id', 'operator' => '=', 'value' => 1],
        ],
    ]);
    $resolved = $result->resolve(request());
    // 'id' is not in meta columns, so filter is ignored — both records returned
    expect($resolved)->toHaveCount(2);
});

it('ignores SQL injection in column name', function () {
    TestModel::create(['name' => 'A']);

    $result = $this->service->search([
        'filter' => [
            ['column' => '1=1); DROP TABLE test_items; --', 'operator' => '=', 'value' => 'x'],
        ],
    ]);
    $resolved = $result->resolve(request());
    // Malicious column is ignored, all records returned
    expect($resolved)->toHaveCount(1);
    // Table still exists
    expect(TestModel::count())->toBe(1);
});

it('ignores order on disallowed column', function () {
    TestModel::create(['name' => 'B']);
    TestModel::create(['name' => 'A']);

    $result = $this->service->search([
        'order' => [
            ['column' => 'id', 'value' => 'asc'],
        ],
    ]);
    $resolved = $result->resolve(request());
    // 'id' is not in meta, order is ignored — default DB order
    expect($resolved)->toHaveCount(2);
});

it('filters create data by allowed columns', function () {
    $result = $this->service->create([
        'name' => 'Test',
        'description' => 'Desc',
        'is_admin' => true,
        'role' => 'superadmin',
    ]);
    $data = $result->resolve(request());
    expect($data['name'])->toBe('Test');
    expect($data['description'])->toBe('Desc');
    // Extra fields should not be persisted
    $model = TestModel::first();
    expect($model->getAttributes())->not->toHaveKey('is_admin');
    expect($model->getAttributes())->not->toHaveKey('role');
});

it('filters update data by allowed columns', function () {
    $model = TestModel::create(['name' => 'Old', 'description' => 'old']);
    $result = $this->service->update($model->id, [
        'name' => 'New',
        'is_admin' => true,
    ]);
    $data = $result->resolve(request());
    expect($data['name'])->toBe('New');
    $fresh = TestModel::find($model->id);
    expect($fresh->getAttributes())->not->toHaveKey('is_admin');
});
