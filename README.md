# dskripchenko/laravel-api

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE.md)
[![Laravel](https://img.shields.io/badge/Laravel-6.x--12.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)](https://www.php.net)

🌐 [Русский](docs/README.ru.md) | [Deutsch](docs/README.de.md) | [中文](docs/README.zh.md)

**A Laravel package for versioned API routing, OpenAPI 3.0 auto-documentation, and CRUD scaffolding.**

Build versioned APIs with automatic OpenAPI documentation generated from PHP docblocks — no YAML/JSON schemas to maintain, no annotation libraries to learn.

## Table of Contents

- [Quick Start](#quick-start)
- [Features](#features)
- [Installation](#installation)
- [Architecture](#architecture)
- [API Versioning](#api-versioning)
- [Routing & Middleware](#routing--middleware)
- [OpenAPI 3.0 Documentation](#openapi-30-documentation)
- [CRUD Scaffolding](#crud-scaffolding)
- [Testing](#testing)
- [Configuration](#configuration)
- [Error Handling](#error-handling)
- [Comparison with Alternatives](#comparison-with-alternatives)
- [API Reference](#api-reference)
- [License](#license)

## Quick Start

```bash
composer require dskripchenko/laravel-api
```

```php
// 1. Define your API class
class Api extends \Dskripchenko\LaravelApi\Components\BaseApi
{
    public static function getMethods(): array
    {
        return [
            'controllers' => [
                'user' => [
                    'controller' => UserController::class,
                    'actions' => ['list', 'show', 'create'],
                ],
            ],
        ];
    }
}

// 2. Define your module
class ApiModule extends \Dskripchenko\LaravelApi\Components\BaseModule
{
    public function getApiVersionList(): array
    {
        return ['v1' => Api::class];
    }
}

// 3. Define your ServiceProvider
class ApiServiceProvider extends \Dskripchenko\LaravelApi\Providers\ApiServiceProvider
{
    protected function getApiModule() { return new ApiModule(); }
}

// 4. Write a controller with docblocks
class UserController extends \Dskripchenko\LaravelApi\Controllers\ApiController
{
    /**
     * List users
     * @input integer ?$page Page number
     * @output integer $id User ID
     * @output string $name User name
     */
    public function list(Request $request): JsonResponse
    {
        return $this->success(User::paginate()->toArray());
    }
}
```

**Result:**
- `GET /api/v1/user/list` — API endpoint
- `GET /api/doc` — Auto-generated API documentation (Scalar)

## Features

| Feature | Description |
|---------|-------------|
| **Versioned routing** | `api/{version}/{controller}/{action}` with inheritance between versions |
| **OpenAPI 3.0** | Auto-generated from `@input`/`@output` docblocks — no YAML files |
| **CRUD scaffolding** | Complete search/create/read/update/delete with filtering, sorting, pagination |
| **Middleware cascade** | Global → controller → action with fine-grained exclusion |
| **Response templates** | Reusable `$ref` schemas in `components/schemas` |
| **Security schemes** | `@security` tag + `securitySchemes` for Bearer/API key auth |
| **Nested parameters** | Dot-notation: `@input string $address.city` → nested JSON schema |
| **File uploads** | `@input file $avatar` → auto `multipart/form-data` |
| **Multiple responses** | `@response 200 {Success}` / `@response 422 {Error}` |
| **Header parameters** | `@header string $Authorization` — aggregated from controller + middleware |
| **Soft deletes** | Built-in `restore()` and `forceDelete()` in CrudService |
| **Request tracing** | `RequestIdMiddleware` — `X-Request-Id` propagation + log context |
| **Optional output fields** | `@output string ?$email` — marks response fields as optional in OpenAPI schema |
| **TypeScript generation** | `api:generate-types` — generates TS interfaces from OpenAPI spec |
| **Test helpers** | `assertApiSuccess()`, `assertApiError()`, `assertApiValidationError()` |
| **Publishable config** | `config/laravel-api.php` — prefix, URI pattern, HTTP methods |

## Installation

### Requirements

- PHP 8.1+
- Laravel 6.x — 12.x

### Install

```bash
composer require dskripchenko/laravel-api
```

### Publish config

```bash
php artisan vendor:publish --tag=laravel-api-config
```

## Architecture

### Request lifecycle

```
HTTP Request
  └─ ApiServiceProvider (registers route: api/{version}/{controller}/{action})
      └─ BaseApiRequest (parses version, controller, action from URI)
          └─ BaseModule::getApi() (version string → BaseApi subclass)
              └─ BaseApi::make()
                  ├─ getMethods() → resolve controller + action
                  ├─ Middleware cascade (global → controller → action)
                  └─ app()->call(Controller@action)
                      └─ JsonResponse {success: true, payload: {...}}
```

### Response format

Every response is wrapped in a standard envelope:

```json
// Success
{"success": true, "payload": {"id": 1, "name": "John"}}

// Error
{"success": false, "payload": {"errorKey": "not_found", "message": "User not found"}}

// Validation error
{"success": false, "payload": {"errorKey": "validation", "messages": {"email": ["Required"]}}}
```

### Directory structure

```
src/
├── Components/        BaseApi, BaseModule, Meta
├── Console/Commands/  ApiInstall, ApiGenerateTypes, BaseCommand
├── Controllers/       ApiController, CrudController, ApiDocumentationController
├── Exceptions/        ApiException, ApiErrorHandler, Handler
├── Facades/        ApiRequest, ApiModule, ApiErrorHandler
├── Interfaces/     CrudServiceInterface, ApiInterface
├── Middlewares/    ApiMiddleware, RequestIdMiddleware
├── Providers/      ApiServiceProvider, BaseServiceProvider
├── Requests/       BaseApiRequest, CrudSearchRequest
├── Resources/      BaseJsonResource, BaseJsonResourceCollection
├── Services/       ApiResponseHelper, CrudService, OpenApiTypeScriptGenerator
└── Traits/
    ├── OpenApiTrait
    └── Testing/       MakesHttpApiRequests
```

## API Versioning

API versions use **PHP class inheritance** — later versions extend earlier ones:

```php
// V1: full API
class ApiV1 extends BaseApi {
    public static function getMethods(): array {
        return ['controllers' => [
            'user' => [
                'controller' => UserControllerV1::class,
                'actions' => ['list', 'show', 'create', 'update', 'delete'],
            ],
        ]];
    }
}

// V2: inherits V1, modifies selectively
class ApiV2 extends ApiV1 {
    public static function getMethods(): array {
        return ['controllers' => [
            'user' => [
                'controller' => UserControllerV2::class,  // upgraded controller
                'actions' => [
                    'delete' => false,                     // removed in v2
                    'archive',                             // new in v2
                ],
            ],
        ]];
    }
}
```

V2 automatically inherits `list`, `show`, `create`, `update` from V1, while overriding the controller and modifying actions.

## Routing & Middleware

### Action configuration

```php
'actions' => [
    'list',                              // simple: method name = action key
    'show' => 'getById',                 // alias: show → calls getById()
    'disabled' => false,                 // disabled action (404)
    'create' => [
        'action' => 'store',             // explicit method name
        'method' => ['post'],            // allowed HTTP methods (default: ['post'])
        'middleware' => [RateLimit::class],
        'exclude-middleware' => [LogMiddleware::class],
        'exclude-all-middleware' => false,
    ],
]
```

### Middleware cascade

```
Global middleware (getMethods root)
  └─ Controller middleware
      └─ Action middleware
```

Each level can exclude middleware from parent levels using `exclude-middleware` (specific) or `exclude-all-middleware` (all).

## OpenAPI 3.0 Documentation

Documentation is generated automatically from PHP docblocks. No YAML or JSON files to maintain.

### Basic tags

```php
/**
 * Create an order
 * Detailed description of the endpoint.
 *
 * @input string $title Order title
 * @input string ?$notes Optional notes
 * @input integer(int64) $amount Amount in cents
 * @input string $status Status [draft,pending,confirmed]
 * @input file ?$attachment Optional file
 *
 * @output integer $id Created order ID
 * @output string(date-time) $createdAt Timestamp
 * @output string ?$notes Optional notes
 */
```

### Nested objects (dot-notation)

```php
/** @input object $address Address
 *  @input string $address.city City
 *  @input string $address.zip ZIP code
 *  @input array $items Order items
 *  @input integer $items[].productId Product
 *  @input integer $items[].quantity Quantity */
```

### Headers, security, responses

```php
/**
 * @header string $Authorization Bearer token
 * @header string ?$X-Request-Id Trace ID
 * @security BearerAuth
 * @response 200 {OrderResponse}
 * @response 422 {ValidationError}
 * @deprecated Use createV2 instead
 */
```

### Response templates

Enable reusable schemas via `components/schemas`:

```php
class Api extends BaseApi {
    public static bool $useResponseTemplates = true;

    public static function getOpenApiTemplates(): array {
        return [
            'OrderResponse' => [
                'id'         => 'integer!',            // required integer
                'title'      => 'string!',             // required string
                'total'      => 'number',              // optional number
                'created_at' => 'string(date-time)',   // with format
                'email'      => 'string(email)!',      // format + required
                'customer'   => '@Customer',           // $ref to another schema
                'items'      => '@OrderItem[]',        // array of $ref
            ],
            'Customer' => [
                'id'   => 'integer!',
                'name' => 'string!',
            ],
            'OrderItem' => [
                'product_id' => 'integer!',
                'quantity'   => 'integer',
                'price'      => 'number',
            ],
        ];
    }

    public static function getOpenApiSecurityDefinitions(): array {
        return [
            'BearerAuth' => ['type' => 'apiKey', 'name' => 'Authorization', 'in' => 'header'],
        ];
    }
}
```

**Shorthand syntax reference:**

| Syntax | Meaning | Example |
|--------|---------|---------|
| `type` | Optional field | `'number'`, `'string'`, `'object'` |
| `type!` | Required field | `'integer!'`, `'string!'` |
| `type(format)` | With format | `'string(date-time)'`, `'string(email)'` |
| `type(format)!` | Format + required | `'string(email)!'` |
| `@Model` | `$ref` to schema | `'@Customer'` |
| `@Model[]` | Array of `$ref` | `'@OrderItem[]'` |

Array format (`['type' => 'string', 'required' => true]`) is also supported and can be mixed with shorthand in the same template.

> Full tag reference: [docs/docblock-tags.md](docs/docblock-tags.md) | Cookbook: [docs/cookbook.md](docs/cookbook.md)

## CRUD Scaffolding

Implement `CrudService` for instant CRUD endpoints:

```php
class ProductService extends CrudService {
    public function meta(): Meta {
        return (new Meta())
            ->string('name', 'Name')
            ->number('price', 'Price')
            ->select('status', 'Status', ['active', 'draft'])
            ->crud();
    }
    public function query(): Builder { return Product::query(); }
    public function resource(Model $model): BaseJsonResource { return new BaseJsonResource($model); }
    public function collection(Collection $c): BaseJsonResourceCollection { return BaseJsonResource::collection($c); }
}
```

### Search with filtering, sorting, pagination

```json
POST /api/v1/product/search
{
  "filter": [
    {"column": "status", "operator": "=", "value": "active"},
    {"column": "price", "operator": "between", "value": [10, 100]},
    {"column": "name", "operator": "like", "value": "phone"},
    {"column": "description", "operator": "is_not_null"}
  ],
  "order": [{"column": "price", "value": "desc"}],
  "page": 1,
  "perPage": 20
}
```

**Available operators:** `=`, `!=`, `>`, `<`, `>=`, `<=`, `in`, `not_in`, `like`, `between`, `is_null`, `is_not_null`

**Security:** LIKE values are auto-escaped (`%`, `_`, `\`). All write operations are wrapped in `DB::transaction()`. Filter array is limited to 50 items.

### Soft delete support

`CrudService` includes `restore($id)` and `forceDelete($id)` for models using `SoftDeletes`. These methods are not exposed via `CrudController` by default — add custom actions in your controller:

```php
'restore' => ['action' => 'restore', 'method' => ['post']],
'forceDelete' => ['action' => 'forceDelete', 'method' => ['post']],
```

## Testing

```php
use Dskripchenko\LaravelApi\Traits\Testing\MakesHttpApiRequests;

class ProductTest extends TestCase
{
    use MakesHttpApiRequests;

    public function test_list(): void
    {
        $response = $this->api('v1', 'product', 'search');
        $this->assertApiSuccess($response);
    }

    public function test_not_found(): void
    {
        $response = $this->api('v1', 'product', 'read', ['id' => 999]);
        $this->assertApiError($response, 'not_found');
    }

    public function test_validation(): void
    {
        $response = $this->api('v1', 'product', 'create', []);
        $this->assertApiValidationError($response, ['name']);
    }
}
```

## TypeScript Generation

Generate TypeScript interfaces from your OpenAPI spec:

```bash
php artisan api:generate-types                                    # All versions → resources/js/shared/api/types.ts
php artisan api:generate-types --version=v1                       # Specific version
php artisan api:generate-types --output=frontend/src/api/types.ts # Custom path
```

Given `@output integer $id` and `@output string ?$email`, the generator produces:

```typescript
export interface UserShowOutput {
  id: number;
  email?: string;
}
```

Component schemas, operation inputs, and outputs are all generated. See [docs/cookbook.md](docs/cookbook.md#recipe-8-generate-typescript-interfaces) for details.

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=laravel-api-config
```

```php
// config/laravel-api.php
return [
    'prefix' => 'api',                                          // URL prefix
    'uri_pattern' => '{version}/{controller}/{action}',          // Route pattern
    'available_methods' => ['get', 'post', 'put', 'patch', 'delete'],
    'openapi_path' => 'public/openapi',                           // OpenAPI JSON output
    'doc_middleware' => [],                                       // Middleware for /api/doc
];
```

## Error Handling

### ApiException

```php
throw new ApiException('payment_failed', 'Insufficient funds');
// → {"success": false, "payload": {"errorKey": "payment_failed", "message": "Insufficient funds"}}
```

### Custom error handlers

```php
use Dskripchenko\LaravelApi\Facades\ApiErrorHandler;
use Dskripchenko\LaravelApi\Services\ApiResponseHelper;
use Illuminate\Database\Eloquent\ModelNotFoundException;

ApiErrorHandler::addErrorHandler(
    ModelNotFoundException::class,
    fn($e) => ApiResponseHelper::sayError(['errorKey' => 'not_found', 'message' => 'Not found'], 404)
);
```

Handlers support **inheritance**: registering a handler for `Exception` will also catch `RuntimeException` via `class_parents()` traversal.

### RequestIdMiddleware

Add to your middleware stack for request tracing:

```php
// Reads X-Request-Id from request header or generates UUID
// Adds request_id to Log::shareContext()
// Sets X-Request-Id on response header
Dskripchenko\LaravelApi\Middlewares\RequestIdMiddleware::class
```

## Comparison with Alternatives

### vs. Classical Laravel approach (manual routes + FormRequest)

| Aspect | Classical Laravel | laravel-api |
|--------|------------------|-------------|
| **Route definition** | `routes/api.php` — one route per endpoint, manual versioning | `getMethods()` — declarative array, versions via class inheritance |
| **Versioning** | Manual: route groups, separate controllers, copy-paste | Automatic: `V2 extends V1`, inherit/override/disable actions |
| **Documentation** | Separate process: write OpenAPI YAML manually or use annotations | Auto-generated from `@input`/`@output` docblocks |
| **Response format** | Ad-hoc per controller, no standard envelope | Standardized `{success, payload}` envelope everywhere |
| **CRUD boilerplate** | Write controller + FormRequest + Resource for each entity | Implement `CrudService` (4 methods), get 6+ endpoints |
| **Middleware per action** | Route-level middleware or controller middleware groups | Fine-grained: global → controller → action with exclusion |
| **Testing** | `$this->getJson('/api/v1/users')` | `$this->api('v1', 'user', 'list')` + assertion helpers |
| **Learning curve** | Standard Laravel knowledge | Learn `getMethods()` structure + docblock tags |
| **Flexibility** | Full control over everything | Constrained to package conventions |
| **When to choose** | Complex APIs with non-standard routing, GraphQL, event-driven APIs | REST APIs with versioning, standard CRUD, auto-documentation needs |

**Advantages of laravel-api:**
- Zero-maintenance documentation — docblocks are the single source of truth
- Version inheritance eliminates code duplication between API versions
- Standardized response format across all endpoints
- CRUD scaffolding reduces boilerplate by 60-80%

**Disadvantages of laravel-api:**
- Fixed URI pattern (`api/{version}/{controller}/{action}`) — not RESTful resource routes
- Opinionated response format — can't easily switch to JSON:API or HAL
- No native support for resource-style URLs (`/users/{id}` vs `/user/show?id=1`)

### vs. L5-Swagger (DarkaOnLine/L5-Swagger)

| Aspect | L5-Swagger | laravel-api |
|--------|-----------|-------------|
| **Approach** | OpenAPI-first: write annotations, generate docs | Code-first: write docblocks, docs + routing together |
| **Annotation style** | Full OpenAPI annotations (`@OA\Get`, `@OA\Schema`, ...) | Lightweight custom tags (`@input`, `@output`, `@header`) |
| **Annotation verbosity** | High: 15-30 lines per endpoint for full spec | Low: 3-10 lines per endpoint |
| **Routing** | None — documentation only, routes defined separately | Integrated — routing + docs from single `getMethods()` |
| **Versioning** | Manual — separate annotation groups | Built-in — class inheritance |
| **CRUD generation** | None | Built-in `CrudService` + `CrudController` |
| **Response format** | Any — you define schemas | Fixed `{success, payload}` envelope |
| **OpenAPI coverage** | Full OpenAPI 3.0 spec support | Subset: covers 90% of common use cases |
| **IDE support** | Plugin support for `@OA\*` annotations | No IDE plugin — but simpler syntax |
| **Ecosystem** | Large community, swagger-php underneath | Smaller, focused package |
| **Spec customization** | Full control over every OpenAPI field | Limited to supported tags |
| **When to choose** | API-first design, full OpenAPI compliance needed, existing routes | Rapid development, versioned APIs, integrated routing + docs |

**Advantages over L5-Swagger:**
- 3-5x less annotation code per endpoint
- Routing and documentation are always in sync (single source)
- Built-in API versioning with inheritance
- CRUD scaffolding included
- No need to learn the full OpenAPI annotation specification

**Disadvantages compared to L5-Swagger:**
- Less OpenAPI coverage (no callbacks, webhooks, links, discriminator)
- No IDE plugin for custom tags
- Fixed response format
- Smaller community and ecosystem
- Not suitable for API-first (design-first) workflow

### vs. Scramble (dedoc/scramble)

| Aspect | Scramble | laravel-api |
|--------|---------|-------------|
| **Approach** | Zero-config: infers spec from code (types, FormRequest, routes) | Docblock tags: explicit `@input`/`@output` annotations |
| **Route integration** | Uses Laravel's native routes | Custom routing via `getMethods()` |
| **Documentation source** | PHP types, FormRequest rules, return types | Docblock annotations |
| **Manual annotations** | Optional, for edge cases | Required for all endpoints |
| **Versioning** | None built-in | Built-in class inheritance |
| **CRUD** | None | Built-in CrudService |
| **Setup effort** | Minimal — install and it works | Moderate — define module, API class, provider |
| **When to choose** | Standard Laravel routes, minimal documentation effort | Custom routing, versioning, CRUD needs |

### Summary: When to use laravel-api

✅ **Choose laravel-api when:**
- You need versioned APIs with inheritance between versions
- You want integrated routing + documentation from a single source
- You need CRUD scaffolding with filtering, sorting, pagination
- You prefer lightweight docblock tags over verbose annotations
- You want a standardized response format across all endpoints

❌ **Choose alternatives when:**
- You need RESTful resource-style URLs (`/users/{id}`)
- You need full OpenAPI 3.0 compliance (callbacks, webhooks, discriminator)
- You follow API-first (design-first) methodology
- You need GraphQL or non-REST APIs
- You want zero-annotation documentation (→ Scramble)

## API Reference

### Controllers

| Class | Methods |
|-------|---------|
| `ApiController` | `success($payload, $status)`, `error($payload, $status)`, `validationError($messages)`, `created($payload)`, `noContent()`, `notFound($message)` |
| `CrudController` | `meta()`, `search(CrudSearchRequest)`, `create(Request)`, `read(Request, int)`, `update(Request, int)`, `delete(int)` |
| `ApiDocumentationController` | `index()` |

### Services

| Class | Methods |
|-------|---------|
| `CrudService` | `meta()`, `query()`, `resource()`, `collection()`, `search()`, `create()`, `read()`, `update()`, `delete()`, `restore()`, `forceDelete()` |
| `ApiResponseHelper` | `say($data, $status)`, `sayError($data, $status)` |

### Components

| Class | Methods |
|-------|---------|
| `BaseApi` (all methods are `static`) | `getMethods()`, `make()`, `getOpenApiTemplates()`, `getOpenApiSecurityDefinitions()`, `beforeCallAction()`, `afterCallAction()`, `getMiddleware()` |
| `BaseModule` | `getApi($version)`, `makeApi()`, `getApiVersionList()`, `getApiPrefix()`, `getApiUriPattern()`, `getAvailableApiMethods()`, `getDocMiddleware()` |
| `Meta` | `string($key, $name)`, `integer($key, $name)`, `number($key, $name)`, `boolean($key, $name)`, `hidden($key, $name)`, `select($key, $name, $items)`, `file($key, $name, $src)`, `action($key, $condition)`, `crud()`, `getOpenApiInputs()`, `getColumnKeys()` |

### Middleware

| Class | Purpose |
|-------|---------|
| `ApiMiddleware` | Abstract base — catches `ApiException` and generic exceptions |
| `RequestIdMiddleware` | Generates/propagates `X-Request-Id`, adds to `Log::shareContext()` |

### Exceptions

| Class | Purpose |
|-------|---------|
| `ApiException` | Exception with `errorKey` string for structured error responses |
| `ApiErrorHandler` | Registry of exception handlers by class, with parent class traversal |

### Facades

| Facade | Resolves to |
|--------|------------|
| `ApiRequest` | `BaseApiRequest` — version, controller, action, HTTP method |
| `ApiModule` | `BaseModule` — version resolution, route configuration |
| `ApiErrorHandler` | `ApiErrorHandler` — exception handler registry |

## License

MIT License. See [LICENSE.md](LICENSE.md) for details.
