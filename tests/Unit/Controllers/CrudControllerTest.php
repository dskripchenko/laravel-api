<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Components\Meta;
use Dskripchenko\LaravelApi\Controllers\CrudController;
use Dskripchenko\LaravelApi\Interfaces\CrudServiceInterface;
use Dskripchenko\LaravelApi\Resources\BaseJsonResource;
use Dskripchenko\LaravelApi\Resources\BaseJsonResourceCollection;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->crudService = Mockery::mock(CrudServiceInterface::class);
    $this->controller = new CrudController($this->crudService);
});

it('delegates meta to crudService', function () {
    $meta = (new Meta())->string('name', 'Name')->crud();
    $this->crudService->shouldReceive('meta')->once()->andReturn($meta);

    $response = $this->controller->meta();
    $data = $response->getData(true);
    expect($data['success'])->toBeTrue();
    expect($data['payload']['columns'])->toHaveKey('name');
    expect($data['payload']['actions'])->toHaveKey('create');
});

it('delegates search to crudService', function () {
    $collection = new BaseJsonResourceCollection([], BaseJsonResource::class);
    $this->crudService->shouldReceive('search')->once()->andReturn($collection);

    $request = Mockery::mock(\Dskripchenko\LaravelApi\Requests\CrudSearchRequest::class);
    $request->shouldReceive('all')->andReturn([]);

    $response = $this->controller->search($request);
    expect($response->getStatusCode())->toBe(200);
});

it('delegates create to crudService', function () {
    $resource = new BaseJsonResource(['id' => 1, 'name' => 'test']);
    $this->crudService->shouldReceive('create')->with(['name' => 'test'])->once()->andReturn($resource);

    $request = Request::create('/test', 'POST', ['name' => 'test']);
    $response = $this->controller->create($request);
    $data = $response->getData(true);
    expect($data['success'])->toBeTrue();
    expect($data['payload']['id'])->toBe(1);
});

it('delegates read to crudService', function () {
    $resource = new BaseJsonResource(['id' => 1, 'name' => 'test']);
    $this->crudService->shouldReceive('read')->with(1)->once()->andReturn($resource);

    $request = Request::create('/test', 'GET');
    $response = $this->controller->read($request, 1);
    $data = $response->getData(true);
    expect($data['success'])->toBeTrue();
    expect($data['payload']['id'])->toBe(1);
});

it('delegates update to crudService', function () {
    $resource = new BaseJsonResource(['id' => 1, 'name' => 'updated']);
    $this->crudService->shouldReceive('update')->with(1, ['name' => 'updated'])->once()->andReturn($resource);

    $request = Request::create('/test', 'POST', ['name' => 'updated']);
    $response = $this->controller->update($request, 1);
    $data = $response->getData(true);
    expect($data['success'])->toBeTrue();
});

it('delegates delete to crudService', function () {
    $resource = new BaseJsonResource(['id' => 1]);
    $this->crudService->shouldReceive('delete')->with(1)->once()->andReturn($resource);

    $response = $this->controller->delete(1);
    $data = $response->getData(true);
    expect($data['success'])->toBeTrue();
});

it('returns swagger meta inputs', function () {
    $meta = (new Meta())->string('name', 'Name', true)->hidden('secret', 'Secret');
    $this->crudService->shouldReceive('meta')->once()->andReturn($meta);

    $inputs = $this->controller->getSwaggerMetaInputs();
    expect($inputs)->toHaveKey('name');
    expect($inputs)->not->toHaveKey('secret');
});
