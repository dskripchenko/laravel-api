---
title: "Build a versioned Laravel API with auto-generated OpenAPI docs in 10 minutes"
published: false
description: "No annotations, no YAML. Write a normal controller, document it with a plain PHPDoc, and get a versioned API plus interactive OpenAPI 3.0 docs for free."
tags: laravel, php, api, openapi
canonical_url:
cover_image:
---

> **TL;DR** — We'll install [`dskripchenko/laravel-api`](https://github.com/dskripchenko/laravel-api), write one controller, and end up with a versioned API (`/api/v1/...`) **and** interactive OpenAPI 3.0 docs at `/api/doc` — generated from the docblock you'd write anyway. Then we'll ship a `v2` without copy-pasting a single controller.

## The problem

Two things rot in every growing Laravel API:

1. **Versioning.** `v1` ships, then `v2` needs to change three endpoints but keep the other twenty. You either copy-paste a `V2` folder (and now bugfixes live in two places) or bolt `if ($version === 2)` branches into your controllers.
2. **Docs.** The OpenAPI spec drifts from the code the moment you merge. Annotation libraries (`#[OA\Get(...)]`, giant YAML files) ask you to describe your API *twice* — once in code, once in attributes.

This package's bet: **your controller already describes itself**. The method name, the request fields, the response shape — write them once, as a normal PHPDoc, and let the package derive routes *and* docs from it. Versioning becomes plain PHP inheritance.

Let's build it.

## What we'll build

A tiny `tasks` API:

- `POST /api/v1/task/list` — list tasks
- `POST /api/v1/task/create` — create one
- interactive docs at `GET /api/doc` (raw spec per version at `/api/doc/{version}`)
- then a `v2` that adds an endpoint **without touching v1**

Total: ~4 small files.

## Step 0 — Install

```bash
composer require dskripchenko/laravel-api
```

Publish the config (optional, but handy to see the knobs):

```bash
php artisan vendor:publish --tag=laravel-api-config
```

```php
// config/laravel-api.php
return [
    'prefix'            => 'api',                            // → /api/...
    'uri_pattern'       => '{version}/{controller}/{action}',
    'available_methods' => ['get', 'post', 'put', 'patch', 'delete'],
    'openapi_path'      => 'public/openapi',
    'doc_middleware'    => [],                               // lock down /api/doc here
];
```

## Step 1 — Write a controller

Nothing exotic — it extends the package's `ApiController`, which gives you response helpers (`success()`, `error()`, `validationError()`, `created()`, `noContent()`, `notFound()`). The **docblock is the documentation**:

```php
<?php

namespace App\Api\V1\Controllers;

use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends ApiController
{
    /**
     * List tasks
     * Returns the current user's tasks.
     *
     * @input integer ?$page Page number
     * @input string $status Filter by status [open,done]
     *
     * @output integer $id Task id
     * @output string $title Task title
     * @output string $status Current status
     *
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        return $this->success([
            ['id' => 1, 'title' => 'Pack for Vietnam', 'status' => 'done'],
            ['id' => 2, 'title' => 'Write this article', 'status' => 'open'],
        ]);
    }

    /**
     * Create a task
     *
     * @input string $title Task title
     * @input string ?$status Initial status [open,done]
     *
     * @output integer $id New task id
     * @output string $title Task title
     *
     * @return JsonResponse
     */
    public function create(Request $request): JsonResponse
    {
        return $this->created([
            'id'    => 3,
            'title' => $request->input('title'),
        ]);
    }
}
```

A few docblock conventions worth knowing:

- `?$page` → optional field.
- `string $status ... [open,done]` → the bracketed list becomes an **enum** in the spec.
- `@input integer(int64) $id` / `@input string(email) $email` → type **with format**.
- `@input file $avatar` → file upload; `@input @User $user` → `$ref` to a component schema.

Every response is wrapped in a consistent envelope:

```json
{ "success": true, "payload": { ... } }
```

Errors (thrown `ApiException` or `$this->error()`) come back as:

```json
{ "success": false, "payload": { "errorKey": "string", "message": "string" } }
```

## Step 2 — Wire up the version, the module, the provider

Three small classes. **This is the whole routing layer** — no `routes/api.php` entries.

```php
<?php
// app/Api/V1/Api.php — defines what v1 exposes
namespace App\Api\V1;

use Dskripchenko\LaravelApi\Components\BaseApi;
use App\Api\V1\Controllers\TaskController;

class Api extends BaseApi
{
    public static function getMethods(): array
    {
        return [
            'controllers' => [
                'task' => [                          // → /api/v1/task/{action}
                    'controller' => TaskController::class,
                    'actions'    => ['list', 'create'],
                ],
            ],
        ];
    }
}
```

```php
<?php
// app/Api/ApiModule.php — maps version strings to Api classes
namespace App\Api;

use Dskripchenko\LaravelApi\Components\BaseModule;

class ApiModule extends BaseModule
{
    public function getApiVersionList(): array
    {
        return [
            'v1' => \App\Api\V1\Api::class,
        ];
    }
}
```

```php
<?php
// app/Providers/ApiServiceProvider.php — plugs the module in
namespace App\Providers;

use Dskripchenko\LaravelApi\Providers\ApiServiceProvider as BaseApiServiceProvider;
use App\Api\ApiModule;

class ApiServiceProvider extends BaseApiServiceProvider
{
    protected function getApiModule(): ApiModule
    {
        return new ApiModule();
    }
}
```

Register the provider (Laravel 11/12/13 — `bootstrap/providers.php`):

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\ApiServiceProvider::class,   // 👈
];
```

That's it. The base provider registers the routes and the `/api/doc` endpoint for you on boot.

## Step 3 — Call it

```bash
curl -X POST http://localhost:8000/api/v1/task/list
```

```json
{
  "success": true,
  "payload": [
    { "id": 1, "title": "Pack for Vietnam", "status": "done" },
    { "id": 2, "title": "Write this article", "status": "open" }
  ]
}
```

> Actions are `POST` by default. Want `GET`? Declare it per action:
> `'list' => ['action' => 'list', 'method' => ['get']]`.

## Step 4 — The payoff: free OpenAPI docs

Open **`GET /api/doc`** in a browser. You don't get a raw JSON file — you get a ready-to-use **interactive API reference** (rendered with [Scalar](https://github.com/scalar/scalar)), with a version switcher across `v1`, `v2`, … already wired up. The package walked your controllers, read those docblocks, and produced a complete OpenAPI 3.0 document — parameters, enums, response schemas, the lot. These docs **can't drift**, because they *are* the code.

Need the raw spec for CI, a client generator, or your own Redoc/Stoplight setup? Each version is served as JSON at **`GET /api/doc/{version}`** (e.g. `/api/doc/v1`) — no `storage:link`, no build step.

Need TypeScript clients? There's a generator:

```bash
php artisan api:generate-types
```

…and an exporter for Postman / HTTP Client / Markdown / cURL:

```bash
php artisan api:export --format=postman
```

## Step 5 — Versioning without copy-paste

Here's the part that usually hurts. `v2` should add an `archive` endpoint — but leave `v1` untouched. You **extend** the previous version, at both the controller and the `Api` level.

The v2 controller inherits every v1 action and adds the new one:

```php
<?php
// app/Api/V2/Controllers/TaskController.php
namespace App\Api\V2\Controllers;

use App\Api\V1\Controllers\TaskController as V1TaskController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends V1TaskController   // inherits list() and create()
{
    /**
     * Archive a task
     *
     * @input integer $id Task id
     *
     * @output boolean $archived Whether it was archived
     */
    public function archive(Request $request): JsonResponse
    {
        return $this->success(['archived' => true]);
    }
}
```

And the v2 `Api` inherits every v1 action, swapping in the new controller:

```php
<?php
// app/Api/V2/Api.php
namespace App\Api\V2;

use App\Api\V1\Api as V1Api;
use App\Api\V2\Controllers\TaskController;

class Api extends V1Api          // inherits every v1 action…
{
    public static function getMethods(): array
    {
        return [
            'controllers' => [
                'task' => [
                    'controller' => TaskController::class,   // …override just this one
                    'actions'    => [
                        'list',
                        'create',
                        'archive',          // add a brand-new action
                        'legacyExport' => false,   // → false disables an action (e.g. one inherited from v1)
                    ],
                ],
            ],
        ];
    }
}
```

Register it:

```php
// app/Api/ApiModule.php
public function getApiVersionList(): array
{
    return [
        'v1' => \App\Api\V1\Api::class,
        'v2' => \App\Api\V2\Api::class,
    ];
}
```

Now `/api/v2/task/...` is live, `v2`'s docs appear automatically, and **v1 never changed**. Bugfix in a shared action? Fix it once in the base class. Need a clean break? Override the controller. Need to kill an endpoint in the new version? Set the action key to `false` (as with `'legacyExport' => false` above).

Middleware cascades the same way — global → controller → action, with `exclude-middleware` / `exclude-all-middleware` escape hatches at each level.

## Why this approach

| | Annotation libs (`#[OA\...]`) | This package |
|---|---|---|
| Describe API | in code **and** in attributes | once, in the PHPDoc |
| Versioning | manual folders / `if` branches | PHP inheritance |
| Docs drift | possible (separate source) | impossible (derived) |
| Routes | hand-written | derived from `getMethods()` |

It won't replace a full framework for every team — if you love attribute-driven specs, you'll miss them here. But if you've ever grep'd a controller wondering whether the docs still match it, this removes the question entirely.

## Try it

```bash
composer require dskripchenko/laravel-api
```

- ⭐ Repo & full docs: https://github.com/dskripchenko/laravel-api
- 📦 Packagist: https://packagist.org/packages/dskripchenko/laravel-api

I maintain it — issues, ideas, and "this broke on my setup" reports are all welcome. What would you want a versioning-first API package to do that yours doesn't?
