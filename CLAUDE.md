# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

**dskripchenko/laravel-api** — Laravel package for versioned API routing, controller/action mapping, middleware management, OpenAPI 3.0 auto-documentation from docblocks, and CRUD scaffolding.

This is a **library package** (not a standalone app). Installed into Laravel projects via Composer. No entry point — artisan commands run in the host application context.

## Build & test

```bash
composer install
vendor/bin/pest              # Run all tests
vendor/bin/pest --filter="test name"   # Single test
vendor/bin/pest tests/Unit/            # Directory
```

## Architecture

### Request lifecycle

```
HTTP Request → ApiServiceProvider (route registration)
  → BaseApiRequest (parses version/controller/action from URI)
    → BaseModule::getApi() (maps version string → BaseApi subclass)
      → BaseApi::make() (resolves controller+action from getMethods(), applies middleware)
        → Controller action (returns JsonResponse)
```

1. **ApiServiceProvider** registers routes with URI pattern `api/{version}/{controller}/{action}`
2. **BaseApiRequest** parses version/controller/action from URI segments
3. **BaseModule** resolves API version class via `getApiVersionList()` (version string → BaseApi subclass)
4. **BaseApi::make()** finds controller+action from `getMethods()`, resolves middleware cascade, calls action via `app()->call()`

### Extension points

To use the package:
- Extend **BaseModule** → override `getApiVersionList()` to map versions to Api classes
- Extend **BaseApi** → override `getMethods()` to define controller/action routing
- Extend **ApiServiceProvider** → override `getApiModule()` to return custom module
- Extend **ApiController** for action implementations (provides `success()`, `error()`, `validationError()`, `created()`, `noContent()`, `notFound()`)

### Response format

All responses are wrapped by `ApiResponseHelper`:

```json
// Success (ApiResponseHelper::say)
{"success": true, "payload": { ... }}

// Error (ApiResponseHelper::sayError)
{"success": false, "payload": {"errorKey": "string", "message": "string"}}

// Validation error
{"success": false, "payload": {"errorKey": "validation", "messages": {"field": ["error"]}}}
```

### getMethods() structure

```php
public static function getMethods(): array
{
    return [
        'middleware' => [GlobalMiddleware::class],           // global middleware
        'controllers' => [
            'user' => [                                      // → api/v1/user/{action}
                'controller' => UserController::class,
                'middleware' => [AuthMiddleware::class],      // controller-level
                'actions' => [
                    'list',                                   // simple: method name = action key
                    'show' => 'getById',                      // alias: action key → method name
                    'disabled' => false,                      // disabled action
                    'create' => [
                        'action' => 'store',                  // explicit method name
                        'method' => ['post'],                 // HTTP methods (default: ['post'])
                        'middleware' => [AdminMiddleware::class],
                        'exclude-middleware' => [],
                        'exclude-all-middleware' => false,
                    ],
                ],
            ],
        ],
    ];
}
```

Middleware cascades: global → controller → action, with `exclude-middleware` and `exclude-all-middleware` at controller and action levels.

### API versioning via inheritance

Later versions extend earlier ones, inheriting and overriding methods:

```php
// v1.1 extends v1 — inherits all actions, can override/disable
class ApiV1_1 extends ApiV1 {
    public static function getMethods(): array {
        return [
            'controllers' => [
                'user' => [
                    'actions' => [
                        'deprecated' => false,        // disable inherited action
                        'newFeature',                  // add new action
                    ],
                ],
            ],
        ];
    }
}
```

### OpenAPI 3.0 documentation

**SwaggerApiTrait** generates OpenAPI 3.0 spec from docblock annotations on controller methods. Available at `GET /api/doc`.

#### Docblock tags reference

```php
/**
 * Action title
 * Action description
 *
 * @input string $name Field description
 * @input integer ?$page Optional field
 * @input string(email) $email Type with format
 * @input integer(int64) $bigId Type with format
 * @input string $status Status [active,blocked,pending]  ← enum in brackets
 * @input file $avatar File upload
 * @input object $address Nested object (use dot-notation below)
 * @input string $address.city Nested field
 * @input array $tags Array of objects
 * @input integer $tags[].id Array item field
 * @input @ModelName Request body as $ref to component schema
 * @input [getSwaggerMetaInputs] Dynamic inputs from Meta component
 *
 * @output integer $id Response field
 * @output string(date-time) $createdAt Formatted output
 * @output @User $user $ref to component schema
 * @output @User[] $users Array of $ref
 * @output object $address Nested response object
 * @output string $address.city Nested response field
 *
 * @header string $Authorization Required header
 * @header string ?$X-Request-Id Optional header
 *
 * @response 200 {UserResponse} HTTP 200 with template ref
 * @response 422 {ValidationError} HTTP 422 with template ref
 *
 * @security BearerAuth
 *
 * @deprecated Use newAction instead
 *
 * @default $page 1
 * @example $page 3
 */
```

**Templates** are enabled via `$useResponseTemplates = true` on the Api class:
```php
class Api extends BaseApi {
    public static bool $useResponseTemplates = true;

    public static function getSwaggerTemplates(): array {
        return [
            'UserResponse' => [
                'id' => ['type' => 'integer', 'required' => true],
                'name' => ['type' => 'string', 'required' => true],
            ],
        ];
    }

    public static function getSwaggerSecurityDefinitions(): array {
        return [
            'BearerAuth' => [
                'type' => 'apiKey',
                'name' => 'Authorization',
                'in' => 'header',
            ],
        ];
    }
}
```

### CRUD scaffolding

**CrudController** + **CrudServiceInterface** implement meta/search/create/read/update/delete/restore/forceDelete.

```php
class ItemCrudService extends CrudService {
    public function meta(): Meta { /* define columns */ }
    public function query(): Builder { return Item::query(); }
    public function resource(Model $model): BaseJsonResource { return new ItemResource($model); }
    public function collection(Collection $collection): BaseJsonResourceCollection { return ItemResource::collection($collection); }
}
```

**CrudSearchRequest** operators: `=`, `!=`, `>`, `<`, `>=`, `<=`, `in`, `not_in`, `like`, `between`, `is_null`, `is_not_null`

Sort: `order.*.value` accepts `'asc'` or `'desc'` (string).

### Testing

Trait **MakesHttpApiRequests** provides:
- `api($version, $controller, $action, $data, $headers)` — sends request through kernel
- `assertApiSuccess($response)` — asserts `{success: true}`
- `assertApiError($response, $errorKey)` — asserts specific error key
- `assertApiValidationError($response, $fields)` — asserts validation error with fields

### Configuration

Publishable config `config/laravel-api.php`:
```php
[
    'prefix' => 'api',
    'uri_pattern' => '{version}/{controller}/{action}',
    'available_methods' => ['get', 'post', 'put', 'patch', 'delete'],
    'swagger_path' => 'public/swagger',
    'doc_middleware' => [],
]
```

### Middleware

- **ApiMiddleware** — abstract base for API middleware (catches ApiException and generic exceptions)
- **RequestIdMiddleware** — generates/propagates `X-Request-Id` header, adds to `Log::shareContext()`

### Error handling

- **ApiErrorHandler** — registry of exception handlers by class. Traverses `class_parents()` for inheritance matching.
- **ApiException** — exception with `errorKey` string field for structured error responses.

## Directory structure

```
src/
├── Components/        BaseApi, BaseModule, Meta
├── Console/Commands/  ApiInstall, BaseCommand
├── Controllers/       ApiController, CrudController, ApiDocumentationController
├── Exceptions/        ApiException, ApiErrorHandler, Handler
├── Facades/           ApiRequest, ApiModule, ApiErrorHandler
├── Interfaces/        CrudServiceInterface, ApiInterface
├── Middlewares/       ApiMiddleware, RequestIdMiddleware
├── Providers/         ApiServiceProvider, BaseServiceProvider
├── Requests/          BaseApiRequest, CrudSearchRequest
├── Resources/         BaseJsonResource, BaseJsonResourceCollection
├── Services/          ApiResponseHelper, CrudService
└── Traits/
    ├── SwaggerApiTrait
    └── Testing/       MakesHttpApiRequests
config/                laravel-api.php (publishable)
example/               Full working example with versioned APIs (v1, v1.1, v1.2, v2)
tests/
├── Unit/              Component, controller, service, middleware, trait tests
├── Feature/           End-to-end CRUD, routing, swagger generation tests
└── Fixtures/          Test helpers, models, controllers, API classes
```

## Conventions

- Namespace: `Dskripchenko\LaravelApi\`
- API versioning uses class inheritance: later versions extend earlier ones
- All write operations in CrudService are wrapped in `DB::transaction()`
- LIKE values are auto-escaped (%, _, \) and wrapped with `%..%`
- Response format: `{success: bool, payload: ...}`
