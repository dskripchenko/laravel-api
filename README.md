<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/assets/logo-dark.svg">
    <img src="docs/assets/logo-light.svg" alt="laravel-api" width="360">
  </picture>
</p>

# dskripchenko/laravel-api

> 🌐 **English** · [Deutsch](docs/de/README.md) · [Русский](docs/ru/README.md) · [中文](docs/zh/README.md)

[![Tests](https://img.shields.io/github/actions/workflow/status/dskripchenko/laravel-api/tests.yml?branch=master&label=tests&logo=github)](https://github.com/dskripchenko/laravel-api/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/dskripchenko/laravel-api?logo=packagist&logoColor=white)](https://packagist.org/packages/dskripchenko/laravel-api)
[![Total Downloads](https://img.shields.io/packagist/dt/dskripchenko/laravel-api)](https://packagist.org/packages/dskripchenko/laravel-api)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)
[![Laravel](https://img.shields.io/badge/Laravel-11.x--13.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-purple.svg)](https://www.php.net)

🌐 [Русский](docs/ru/README.md) | [Deutsch](docs/de/README.md) | [中文](docs/zh/README.md)

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
- [MCP server](#mcp-server)
- [Configuration](#configuration)
- [Error Handling](#error-handling)
- [IDE support](#ide-support)
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
- `GET /api/doc` — Auto-generated API documentation ([@dskripchenko/openapi](https://github.com/dskripchenko/openapi) reference)

## Features

| Feature | Description |
|---------|-------------|
| **Versioned routing** | `api/{version}/{controller}/{action}` with inheritance between versions |
| **OpenAPI 3.0** | Auto-generated from `@input`/`@output` docblocks — no YAML files |
| **Documentation page** | `/api/doc` — every version with a switcher, search, try-it, code samples, TypeScript types; works offline with `api:doc-assets` |
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
| **Named routes** | Each action registered as a named Laravel route — `route('api.v1.user.list')` |
| **API export** | `api:export` — Postman Collection, HTTP Client (.http), Markdown, cURL |
| **Markup linting** | `api:lint` — catches renamed actions, dangling templates, unknown types |
| **IDE support** | [PhpStorm plugin](https://github.com/dskripchenko/laravel-api-idea) — highlighting, inspections, quick fixes, endpoint list |
| **Test helpers** | `assertApiSuccess()`, `assertApiError()`, `assertApiValidationError()` |
| **Publishable config** | `config/laravel-api.php` — prefix, URI pattern, HTTP methods |

## Installation

### Requirements

- PHP 8.2+
- Laravel 11.x — 13.x

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

### Operation model

Routing, the documentation and the linter read the API through one model:

```
getMethods()  ──► OperationRegistry ──► Operation          keys, controller@method, HTTP methods, middleware
docblocks     ──► OperationDocParser ─► OperationDoc       tag lines, also from middleware
                  DescriptorBuilder ──► OperationDescriptor input / output schemas, security, notes
                        │
        ┌───────────────┼──────────────────┐
  OpenApiRenderer   OpenApiLinter     BaseApi::make() (dispatch, via the registry)
        │
  /api/doc, api:export, api:generate-types
```

`getMethods()` is read in one place (`OperationRegistry`), the docblocks in
another (`OperationDocParser`); `DescriptorBuilder` turns the two into one
`OperationDescriptor` per HTTP method — the input and output as
transport-neutral JSON Schema, plus how the input was declared.
`OpenApiRenderer` decides how that travels over HTTP. The model lives in
`src/Operations/`; `php artisan api:cache` freezes the map for production
(see [Production](#production)).

### Calling an action without HTTP

```php
use Dskripchenko\LaravelApi\Runtime\ApiInvocation;
use Dskripchenko\LaravelApi\Runtime\Dispatcher;

$result = app(Dispatcher::class)->dispatch(
    new ApiInvocation('v1', 'user', 'create', ['name' => 'Ann'])
);

$result->success;     // true
$result->payload;     // ['id' => 1, 'name' => 'Ann']
$result->errorKey();  // null, or 'auth_error', 'validation_error', …
```

The call goes through the router the way a request to the action's URL would —
middleware cascade, validation, `auth()`, `ApiRequest` — and the answer is read
from the `{success, payload}` envelope an HTTP client gets. It runs inside the
current request as the same caller (its headers and cookies are passed on), and
that request is put back afterwards. GET input travels as a query string, the
rest as a JSON body. This is what non-HTTP transports build on.

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
├── Console/Commands/  ApiInstall, ApiGenerateTypes, ApiExport, ApiDocClear, ApiLint
├── Controllers/       ApiController, CrudController, ApiDocumentationController
├── Exceptions/        ApiException, ApiErrorHandler, Handler
├── Facades/        ApiRequest, ApiModule, ApiErrorHandler
├── Interfaces/     CrudServiceInterface, ApiInterface
├── Middlewares/    ApiMiddleware, RequestIdMiddleware
├── Operations/     OperationRegistry, DescriptorBuilder; Doc/, Descriptor/, Schema/
├── Providers/      ApiServiceProvider, BaseServiceProvider
├── Requests/       BaseApiRequest, CrudSearchRequest
├── Resources/      BaseJsonResource, BaseJsonResourceCollection
├── Services/       ApiResponseHelper, CrudService, OpenApiTypeScriptGenerator, ApiCache, OpenApi/ (OpenApiRenderer), Linter/, Export/
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
    'restored' => true,                  // re-enable an action an earlier version disabled; method = action key
    'create' => [
        'action' => 'store',             // explicit method name
        'method' => ['post'],            // allowed HTTP methods (default: ['post'])
        'name' => 'orders.store',        // route name: api.{version}.orders.store
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

A list of scalars takes `[]` with no child: `@input string $tags[]`.

### Fields known only at runtime

When the fields depend on the route — one generic controller registered under a
key per entity — `@input [method]` asks a controller method, and the method is
told which operation is being described:

```php
use Dskripchenko\LaravelApi\Services\OpenApi\OperationContext;

/**
 * @input integer $id
 * @input [entityFields]
 */
public function update(Request $request) { /* ... */ }

public function entityFields(OperationContext $context): array
{
    // $context->controllerKey is `users` on /v1/users/update, `posts` on /v1/posts/update
    return [
        'type' => 'object',
        'properties' => ['email' => ['type' => 'string', 'format' => 'email', 'maxLength' => 255]],
        'required' => ['email'],
    ];
}
```

The method may return docblock lines (`['string $email Email']`) or a JSON
Schema object, which goes into the spec as it is — constraints and all. `@output
[method]` works the same way. See [docs/docblock-tags.md](docs/en/docblock-tags.md#dynamic-inputs-from-a-method).

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
    public static $useResponseTemplates = true;

    public static function getSchemaTemplates(): array {
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

    public static function getSecuritySchemes(): array {
        return [
            'BearerAuth' => ['type' => 'apiKey', 'name' => 'Authorization', 'in' => 'header'],
        ];
    }
}
```

**Names before 6.1.** `getOpenApiTemplates()` and `getOpenApiSecurityDefinitions()` keep working: they are the same hooks under their earlier names. Override either name; the package reads `getSchemaTemplates()` / `getSecuritySchemes()`, which answer with the earlier ones unless overridden themselves. The [IDE plugin](#ide-support) reads both names from 1.1.0.

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

**Response envelope.** Every response really leaves as `{success, payload}` (see [Response format](#response-format)). `public static $responseEnvelope = true;` on the Api class makes the spec say so: templates then describe payloads, and every response — `@output` fields, `{Template}` refs, error codes — is wrapped. Templates may nest (`'client!' => ['email' => 'string(email)']`, `['string']`, `[['id' => 'integer!']]`), and a custom envelope is a field map with `'{payload}'` where the body goes. Details: [docblock-tags.md](docs/en/docblock-tags.md#response-envelope).

### Documentation page

`GET /api/doc` renders the reference of
[@dskripchenko/openapi](https://github.com/dskripchenko/openapi): one script,
no build step in your project. Every listed API version is one spec on the page,
and the page offers:

- a switcher between the versions and full-text search;
- "Try it" on every operation: a form for the parameters and the body, plus your
  own query parameters, headers and cookies;
- an authentication panel for the spec's security schemes, OAuth2 included;
- request samples in 13 languages;
- a TypeScript types tab, and the types as a `.d.ts` download;
- PDF of one operation or of the whole spec, through the browser's print dialog;
- a light, dark or system theme, and an English or Russian interface.

How each of these works is described in the
[@dskripchenko/openapi documentation](https://github.com/dskripchenko/openapi).
Versions listed in `laravel-api.hidden_versions` stay off the page; their specs
are still served at `/api/doc/{version}`.

The page is configured in the `documentation` block of `config/laravel-api.php`:

| Key | Env | Default | |
|-----|-----|---------|---|
| `script` | `LARAVEL_API_DOC_SCRIPT` | `https://cdn.jsdelivr.net/npm/@dskripchenko/openapi@1.0.0/dist/standalone.js` | The page's script — one file, styles included. |
| `title` | `LARAVEL_API_DOC_TITLE` | `null` | The page title; the open version's title when empty. |
| `locale` | `LARAVEL_API_DOC_LOCALE` | `'auto'` | `'en'`, `'ru'` or `'auto'` (the browser's language). |
| `theme` | `LARAVEL_API_DOC_THEME` | `'system'` | `'light'`, `'dark'` or `'system'` (follows the OS). The reader can switch, and the choice is remembered. |
| `try_it` | `LARAVEL_API_DOC_TRY_IT` | `true` | `false` hides the "Try it" panels. |
| `proxy` | `LARAVEL_API_DOC_PROXY` | `null` | A URL that relays try-it requests; see below. `null` sends them straight from the page. |
| `oauth_redirect_url` | `LARAVEL_API_DOC_OAUTH_REDIRECT_URL` | `null` | The OAuth2 redirect URI the try-it sign-in returns to; `null` means the page's own URL. The identity provider must allow it. |
| `persist_credentials` | `LARAVEL_API_DOC_PERSIST_CREDENTIALS` | `false` | Keep the keys and tokens a reader types: `false`, `'session'` or `'local'`. |
| `snippet_targets` | — | `null` | The languages offered for code samples, in order; `null` offers all. |
| `default_snippet` | — | `'curl'` | The language shown first. |

**When the script does not load.** The server renders a plain list of links to
each version's raw OpenAPI spec, and the script replaces it once it starts. A
blocked CDN therefore gives a page with links, not a blank one.

**Networks without a CDN.** `api:doc-assets` downloads the configured script once
and puts it into `public/`:

```bash
php artisan api:doc-assets
# Saved … bytes to public/vendor/laravel-api/openapi/standalone.js
# Point the page at it: LARAVEL_API_DOC_SCRIPT=/vendor/laravel-api/openapi/standalone.js
```

```dotenv
LARAVEL_API_DOC_SCRIPT=/vendor/laravel-api/openapi/standalone.js
```

`--from=<url>` downloads another URL (another version, an internal mirror);
`--path=<path>` changes where the file goes, relative to `public/`. Run the
command where the internet is reachable — a build stage, say — and ship the file
with the application. Once `script` points at a local path, the command needs
`--from` to download again.

**Try-it through a proxy.** Try-it requests go from the reader's browser, so an
API on another origin has to allow them with CORS. Where it cannot, set `proxy`
to a URL that relays them. The package does not ship such an endpoint — it is
yours to write and to protect. It must:

- accept a `POST` whose JSON body is the request (`HttpRequest`: method,
  absolute URL, headers, body);
- check the URL's host against an allowlist, and require the same sign-in as the
  rest of your back office — otherwise it is an open relay;
- perform the request and answer `200` with the response as JSON
  (`HttpResponse`: status, headers, body).

The exact contract is in
[the standalone page docs](https://github.com/dskripchenko/openapi/blob/main/docs/en/standalone.md#the-proxy).
When the documentation routes run with a session (`'doc_middleware' => ['web']`),
the page carries a `<meta name="csrf-token">`, and the page sends its value in
`X-CSRF-TOKEN`, so a proxy route behind Laravel's CSRF check accepts the call.

**Access.** `doc_middleware` applies to `/api/doc` and `/api/doc/{version}`:
add an authentication middleware there to close the page and the specs.

### Linking to a single endpoint

The reference page addresses every operation by a hash, and `DocLink` builds it
without opening a browser — for a ticket, a README, or an IDE:

```php
use Dskripchenko\LaravelApi\Services\OpenApi\DocLink;

DocLink::url('v1', 'order', 'create', 'post');
// https://example.test/api/doc#v1/tag/order/POST/v1/order/create

DocLink::anchor('v1', 'order', 'create', 'post');  // the hash alone, without '#'
```

The first segment is the API version, and it is deliberate: the page is handed
an explicit slug per version, so a link survives the docblock summary being
reworded. An action declaring several HTTP methods is several operations on the
page — one anchor each.

Anchors built before 6.0 keep working: the page reads the
`{version}/tag/{controller}/{METHOD}{path}` form, so bookmarks, tickets and the
IDE plugin's links land on the same operations.

### Prefilled links

`DocLink::prefilled()` builds a link that opens an endpoint with its try-it form
already filled in — for a bug report, a chat or a test report.
`DocLink::prefillPayload()` returns the `?f=` value alone.

```php
use Dskripchenko\LaravelApi\Services\OpenApi\DocLink;

DocLink::prefilled('v1', 'order', 'create', 'post', [
    'header'  => ['Idempotency-Key' => 'k-1'],
    'query'   => ['draft' => true],
    'body'    => ['customerId' => 42, 'items' => [['sku' => 'A-1', 'qty' => 2]]],
    'xHeader' => [['X-Debug', 'on']],
]);
// https://example.test/api/doc#v1/tag/order/POST/v1/order/create?f=z…

DocLink::prefillPayload(['path' => ['id' => 42]]);  // 'z…'
```

| Key | |
|-----|---|
| `path`, `query`, `header`, `cookie` | Parameter values by name. |
| `body` | The body text as typed; an array is JSON-encoded. |
| `mediaType` | The body's media type. |
| `server` | An index into the spec's servers, or a server URL. |
| `vars` | Server variables by name. |
| `xQuery`, `xHeader`, `xCookie` | Parameters the spec does not declare, as a list of `[name, value]` pairs. |
| `auth` | Credentials by security scheme name. Left out unless you pass it explicitly: a link travels further than its author expects. |

The values of `path`, `query`, `header`, `cookie` and `vars` become strings
(`true` → `'true'`, `2` → `'2'`), since the page reads every value as text.

The page never sends the request by itself: it fills the form, shows a notice
asking to check the values, and the reader presses "Send". The payload rides in
the hash, after the anchor, so it does not reach your server or its logs. The
format is described in
[the deep links docs](https://github.com/dskripchenko/openapi/blob/main/docs/en/links.md#prefilled-links).

> Full tag reference: [docs/docblock-tags.md](docs/en/docblock-tags.md) | Linting: [docs/linting.md](docs/en/linting.md) | Cookbook: [docs/cookbook.md](docs/en/cookbook.md)

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
php artisan api:generate-types --api-version=v1                       # Specific version
php artisan api:generate-types --output=frontend/src/api/types.ts # Custom path
```

Given `@output integer $id` and `@output string ?$email`, the generator produces:

```typescript
export interface UserShowOutput {
  id: number;
  email?: string;
}
```

Component schemas, operation inputs, and outputs are all generated. See [docs/cookbook.md](docs/en/cookbook.md#recipe-8-generate-typescript-interfaces) for details.

## IDE support

The markup this package reads is not part of any grammar PhpStorm knows, so it
shows as one grey blob of prose — and everything the package fails quietly at
fails quietly there too. There is a plugin for that:
**[dskripchenko/laravel-api-idea](https://github.com/dskripchenko/laravel-api-idea)**.

**Installing.** It is distributed from its own repository rather than from
JetBrains Marketplace. Add the plugin repository once — Settings | Plugins | ⚙ |
*Manage Plugin Repositories…* | **+** —

```
https://raw.githubusercontent.com/dskripchenko/laravel-api-idea/main/updatePlugins.xml
```

then install *Laravel API* from the Marketplace tab as usual; updates arrive the
same way. Paste the address without a trailing space — the IDE encodes it into
the URL, and the resulting 404 is reported as *Connection failed*. Or take
`laravel-api-idea-<version>.zip` from any
[release](https://github.com/dskripchenko/laravel-api-idea/releases) and use
*Install Plugin from Disk…*.

PhpStorm, or IntelliJ IDEA Ultimate with the PHP plugin. IDEA Community cannot
run it — the plugin hangs off the PHP plugin's PSI, and JetBrains does not
publish that for Community.

**While you type**

- Highlights `@input`, `@output`, `@header`, `@response`, `@security`,
  `@default`, `@example` — types, formats, variables, template references and
  status codes, so a line the generator has stopped understanding stops looking
  like the ones it still does.
- Reports markup that does not parse, where it is written, instead of letting it
  vanish from the spec.
- Flags unknown types with the consequence named: the generator will call the
  field `string`.
- Compares the markup against `$request->validate([...])`. A validated field the
  docblock never mentions is reported on the rule itself, with a quick fix that
  writes the tag from it — `email` becomes `string(email)`, a rule that is not
  `required` becomes optional, `in:a,b` becomes an enumeration.
- Marks a `@response` template or a `@security` scheme nothing declares, and
  offers to declare the template — creating `getSchemaTemplates()` when the
  class has none.
- Turns the silent 404 red: an action pointing at a missing, non-public or
  static method is an error while typing, with a quick fix that writes the
  method, shaped like the ones already in the class.
- Completes types, formats, template names, security schemes and status codes.

**Getting around**

- Ctrl+Click from `{TemplateName}`, `@Model`, `@security Scheme` and
  `@input [method]` to their declarations.
- Find Usages on a response template — every docblock naming it. A template read
  only by the generator otherwise looks unused to the IDE.
- Gutter arrows both ways between the route map and the controllers. An action
  with no arrow points at a method that is not there.
- A line above the controller method opening this endpoint's own page in
  `/api/doc` — see [Linking to a single endpoint](#linking-to-a-single-endpoint)
  for how that address is built.
- Every endpoint the route map declares, in one searchable tool window, with the
  version it answers under.

**On demand**

- Runs `api:lint` and makes its findings clickable.
- Exports the endpoint under the caret as a request — Bruno, cURL, HTTP Client,
  Postman or Markdown — through `api:export --endpoint`.

## Linting

The markup fails quietly: an action whose method was renamed answers 404 — the
same 404 as a wrong URL — and a `@response` naming a template that does not
exist becomes a `$ref` into nothing, in a spec that still validates.

```bash
php artisan api:lint            # report
php artisan api:lint --strict   # fail on warnings too, for CI
php artisan api:lint --unrouted # also: public methods no action points at
```

It reads the route map and the docblocks with the same parser the OpenAPI
generator uses, and also reports an action that validates its input without
declaring any (`input.undeclared`). Full list of rules:
[docs/linting.md](docs/en/linting.md).

## MCP server

Every API version can be an [MCP](https://modelcontextprotocol.io) server, so
agents call the API as tools:

```php
// config/laravel-api.php
'mcp' => [
    'enabled' => true,                     // LARAVEL_API_MCP
    'versions' => ['v2'],                  // empty: every version not in hidden_versions
    'middleware' => ['auth:sanctum'],      // on the MCP endpoint itself
    'allowed_origins' => [],               // browser origins besides the app's own
],
```

```bash
claude mcp add --transport http shop https://shop.test/api/mcp/v2 \
  --header "Authorization: Bearer $TOKEN"
```

Each action is a tool named `controller.action` (`user.show`), described by
its docblock: the summary is the title, the `@input` lines are the input
schema (templates travel under `$defs`), GET actions are marked read-only. A
tool call goes through the same router, middleware cascade and validation as an
HTTP request, as the caller of the MCP request — its headers, the
`Authorization` above included, are passed on — and returns the action's
`payload` (`isError` when `success` is false). Leave an action or a controller
out with `'mcp' => false` in `getMethods()`.

The endpoint is `POST /{prefix}/mcp/{version}` (Streamable HTTP, JSON answers,
no sessions); a browser `Origin` other than the application's own is refused.
`api:cache` caches the tool lists too. Not offered: resources, prompts,
streaming, file uploads, and `outputSchema` — a docblock's `@output` is not
trusted to be exact enough for a client that validates against it.

## Production

```bash
php artisan api:cache            # freeze the API map, build the OpenAPI specs
php artisan api:cache --no-docs  # the map only
php artisan api:clear            # back to building it on every request
```

Without it every request rebuilds the route definitions of every version —
the middleware cascade of each action included; for an API of a couple of
thousand actions that is about 13 ms a request. `api:cache` writes the map to
`bootstrap/cache/laravel-api.php` and builds the spec of every version where
`/api/doc` reads it; a version whose spec cannot be built fails the command,
and nothing is written. With `route:cache` on, the package no longer registers
its routes a second time on every request either.

Like `route:cache`, it freezes what `getMethods()` returns when the command
runs — run it on every deployment, and do not use it for an API whose map
depends on the request or on data. It needs OPcache, as Laravel's own caches
do: without it, reading the file costs more than building the map.
`optimize:clear` runs `api:clear`; `optimize` does not run `api:cache`.

## API Export

Export your API spec in multiple formats:

```bash
php artisan api:export --format=postman    # Postman Collection v2.1
php artisan api:export --format=http       # JetBrains/VS Code .http files
php artisan api:export --format=markdown   # Standalone documentation
php artisan api:export --format=curl       # Bash script with curl commands
php artisan api:export --format=bruno      # Bruno collection — a directory of .bru files
```

Options: `--api-version=v1` (specific version), `--output=path` (custom file). By default, generates per-version files (`v1.json`, `v1.http`, `v1.md`, `v1.sh`).

Bruno is a directory rather than a document — a manifest, an environment and one
`.bru` per request — so `--output` names a directory and `--stdout` is refused.
The point of it is that a collection can live in the repository next to the code
that produces it, and a diff of it reads.

### One endpoint

A collection of two hundred requests is not what someone wants who is about to
try one:

```bash
php artisan api:export --endpoint=v1.order.create --format=bruno --stdout
php artisan api:export --endpoint=v1.order.create --format=curl --stdout
php artisan api:export --endpoint=v1.order.create --format=bruno --output=collection/order-create.bru
```

The endpoint is spelled the way the package names its routes —
`version.controller.action` — so what is copied out of a log or a route list is
what the command takes. `--method=get` picks one when the action answers
several. Every format supports this, and none of them has a special case for it:
the spec is narrowed to one path first, and each exporter then does exactly what
it already did.

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
    'doc_middleware' => [],                                       // Middleware for /api/doc and /api/doc/{version}, e.g. ['web']
    'hidden_versions' => [],                                      // Versions kept off /api/doc
    'documentation' => [                                          // The /api/doc page — see "Documentation page"
        'script' => env('LARAVEL_API_DOC_SCRIPT', 'https://cdn.jsdelivr.net/npm/@dskripchenko/openapi@1.0.0/dist/standalone.js'),
        'title' => env('LARAVEL_API_DOC_TITLE'),
        'locale' => env('LARAVEL_API_DOC_LOCALE', 'auto'),            // 'en', 'ru', 'auto'
        'theme' => env('LARAVEL_API_DOC_THEME', 'system'),            // 'light', 'dark', 'system'
        'try_it' => env('LARAVEL_API_DOC_TRY_IT', true),
        'proxy' => env('LARAVEL_API_DOC_PROXY'),
        'oauth_redirect_url' => env('LARAVEL_API_DOC_OAUTH_REDIRECT_URL'),
        'persist_credentials' => env('LARAVEL_API_DOC_PERSIST_CREDENTIALS', false), // false, 'session', 'local'
        'snippet_targets' => null,                                    // null offers all languages
        'default_snippet' => 'curl',
    ],
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
| **IDE support** | Plugin support for `@OA\*` annotations | [Plugin](https://github.com/dskripchenko/laravel-api-idea): highlighting, inspections, quick fixes, navigation, endpoint list, `api:lint` runner |
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
| `BaseApi` (all methods are `static`) | `getMethods()`, `make()`, `getSchemaTemplates()`, `getSecuritySchemes()`, `operations()`, `beforeCallAction()`, `afterCallAction()`, `getMiddleware()` |
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
