<?php

declare(strict_types=1);

use Tests\Fixtures\Crud\TestModel;
use Tests\Fixtures\Crud\TestCrudService;
use Dskripchenko\LaravelApi\Controllers\CrudController;
use Dskripchenko\LaravelApi\Interfaces\CrudServiceInterface;

beforeEach(function () {
    $migration = include __DIR__ . '/../../Fixtures/Crud/create_test_items_table.php';
    $migration->up();

    // Bind CrudServiceInterface to our TestCrudService
    $this->app->bind(CrudServiceInterface::class, TestCrudService::class);
});

afterEach(function () {
    $migration = include __DIR__ . '/../../Fixtures/Crud/create_test_items_table.php';
    $migration->down();
});

it('returns meta endpoint', function () {
    $service = new TestCrudService();
    $controller = new CrudController($service);
    $response = $controller->meta();
    $data = $response->getData(true);

    expect($data['success'])->toBeTrue();
    expect($data['payload']['columns'])->toHaveKey('name');
    expect($data['payload']['actions'])->toHaveKey('create');
});

it('creates a record', function () {
    $service = new TestCrudService();
    $controller = new CrudController($service);
    $request = \Illuminate\Http\Request::create('/test', 'POST', ['name' => 'Test', 'description' => 'Desc']);
    $response = $controller->create($request);
    $data = $response->getData(true);

    expect($data['success'])->toBeTrue();
    expect($data['payload']['name'])->toBe('Test');
    expect(TestModel::count())->toBe(1);
});

it('reads a record', function () {
    $model = TestModel::create(['name' => 'Read', 'description' => 'ReadDesc']);
    $service = new TestCrudService();
    $controller = new CrudController($service);
    $request = \Illuminate\Http\Request::create('/test', 'GET');
    $response = $controller->read($request, $model->id);
    $data = $response->getData(true);

    expect($data['success'])->toBeTrue();
    expect($data['payload']['name'])->toBe('Read');
});

it('returns 404 for non-existent read', function () {
    $service = new TestCrudService();
    $controller = new CrudController($service);
    $request = \Illuminate\Http\Request::create('/test', 'GET');
    $controller->read($request, 999);
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

it('updates a record', function () {
    $model = TestModel::create(['name' => 'Old', 'description' => 'OldDesc']);
    $service = new TestCrudService();
    $controller = new CrudController($service);
    $request = \Illuminate\Http\Request::create('/test', 'POST', ['name' => 'Updated']);
    $response = $controller->update($request, $model->id);
    $data = $response->getData(true);

    expect($data['success'])->toBeTrue();
    expect($data['payload']['name'])->toBe('Updated');
});

it('deletes a record', function () {
    $model = TestModel::create(['name' => 'Delete', 'description' => 'DelDesc']);
    $service = new TestCrudService();
    $controller = new CrudController($service);
    $response = $controller->delete($model->id);
    $data = $response->getData(true);

    expect($data['success'])->toBeTrue();
    expect(TestModel::count())->toBe(0);
});

it('searches with pagination', function () {
    for ($i = 1; $i <= 10; $i++) {
        TestModel::create(['name' => "Item {$i}"]);
    }

    $service = new TestCrudService();
    $result = $service->search(['page' => 1, 'perPage' => 5]);
    $resolved = $result->resolve(request());

    expect($resolved)->toHaveCount(5);
    expect($result->additional['total'])->toBe(10);
    expect($result->additional['lastPage'])->toBe(2);
});

it('searches with filter', function () {
    TestModel::create(['name' => 'Alpha']);
    TestModel::create(['name' => 'Beta']);

    $service = new TestCrudService();
    $result = $service->search([
        'filter' => [
            ['column' => 'name', 'operator' => '=', 'value' => 'Alpha'],
        ],
    ]);
    $resolved = $result->resolve(request());

    expect($resolved)->toHaveCount(1);
    expect($resolved[0]['name'])->toBe('Alpha');
});

it('searches with ordering', function () {
    TestModel::create(['name' => 'Banana']);
    TestModel::create(['name' => 'Apple']);

    $service = new TestCrudService();
    $result = $service->search([
        'order' => [
            ['column' => 'name', 'value' => true],
        ],
    ]);
    $resolved = $result->resolve(request());

    expect($resolved[0]['name'])->toBe('Apple');
});

it('searches with custom perPage', function () {
    for ($i = 1; $i <= 20; $i++) {
        TestModel::create(['name' => "Item {$i}"]);
    }

    $service = new TestCrudService();
    $result = $service->search(['perPage' => 3]);
    $resolved = $result->resolve(request());

    expect($resolved)->toHaveCount(3);
    expect($result->additional['perPage'])->toBe(3);
});
