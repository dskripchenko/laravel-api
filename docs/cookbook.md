# Cookbook — Step-by-step Recipes

## Recipe 1: Create a versioned API from scratch

### Step 1: Create the API class

```php
// app/Api/V1/Api.php
namespace App\Api\V1;

use App\Api\V1\Controllers\UserController;
use App\Api\V1\Controllers\OrderController;
use App\Http\Middleware\AuthMiddleware;
use Dskripchenko\LaravelApi\Components\BaseApi;

/**
 * My API v1
 * Version 1 of the application API
 */
class Api extends BaseApi
{
    public static function getMethods(): array
    {
        return [
            'middleware' => [AuthMiddleware::class],
            'controllers' => [
                'user' => [
                    'controller' => UserController::class,
                    'actions' => [
                        'list' => [
                            'action' => 'list',
                            'method' => ['get'],
                        ],
                        'show' => [
                            'action' => 'show',
                            'method' => ['get'],
                        ],
                        'create',
                        'update',
                        'delete',
                    ],
                ],
                'order' => [
                    'controller' => OrderController::class,
                    'actions' => [
                        'list' => ['action' => 'list', 'method' => ['get']],
                        'create',
                    ],
                ],
            ],
        ];
    }
}
```

### Step 2: Create the Module

```php
// app/Api/ApiModule.php
namespace App\Api;

use App\Api\V1\Api as V1;
use Dskripchenko\LaravelApi\Components\BaseModule;

class ApiModule extends BaseModule
{
    public function getApiVersionList(): array
    {
        return [
            'v1' => V1::class,
        ];
    }
}
```

### Step 3: Create the ServiceProvider

```php
// app/Providers/ApiServiceProvider.php
namespace App\Providers;

use App\Api\ApiModule;
use Dskripchenko\LaravelApi\Providers\ApiServiceProvider as BaseProvider;

class ApiServiceProvider extends BaseProvider
{
    protected function getApiModule(): ApiModule
    {
        return new ApiModule();
    }
}
```

### Step 4: Create a controller

```php
// app/Api/V1/Controllers/UserController.php
namespace App\Api\V1\Controllers;

use App\Models\User;
use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends ApiController
{
    /**
     * List all users
     * Returns a paginated list of users.
     *
     * @input integer ?$page Page number
     * @input integer ?$perPage Items per page
     *
     * @output integer $id User ID
     * @output string $name User name
     * @output string(email) $email User email
     */
    public function list(Request $request): JsonResponse
    {
        $users = User::paginate($request->input('perPage', 15));
        return $this->success($users->toArray());
    }

    /**
     * Get user by ID
     *
     * @input integer $id User ID
     *
     * @output integer $id User ID
     * @output string $name User name
     * @output string(email) $email User email
     * @output string(date-time) $createdAt Registration date
     */
    public function show(Request $request): JsonResponse
    {
        $user = User::findOrFail($request->input('id'));
        return $this->success($user->toArray());
    }

    /**
     * Create a user
     *
     * @input string $name User name
     * @input string(email) $email Email address
     * @input string $password Password
     *
     * @output integer $id Created user ID
     */
    public function create(Request $request): JsonResponse
    {
        $user = User::create($request->only(['name', 'email', 'password']));
        return $this->created(['id' => $user->id]);
    }
}
```

### Step 5: Register the provider

```php
// bootstrap/providers.php (Laravel 11+)
return [
    App\Providers\ApiServiceProvider::class,
];
```

### Result

- `GET  /api/v1/user/list` — list users
- `GET  /api/v1/user/show?id=1` — get user
- `POST /api/v1/user/create` — create user
- `POST /api/v1/user/update` — update user
- `POST /api/v1/user/delete` — delete user
- `POST /api/v1/order/create` — create order
- `GET  /api/doc` — API documentation (Scalar)

---

## Recipe 2: Add a new API version

```php
// app/Api/V2/Api.php
namespace App\Api\V2;

use App\Api\V1\Api as V1;
use App\Api\V2\Controllers\UserController;

class Api extends V1  // ← inherits all v1 actions
{
    public static function getMethods(): array
    {
        return [
            'controllers' => [
                'user' => [
                    'controller' => UserController::class, // new controller
                    'actions' => [
                        'delete' => false,                 // disable delete in v2
                        'archive',                         // add new action
                    ],
                ],
            ],
        ];
    }
}
```

Register in module:
```php
public function getApiVersionList(): array
{
    return [
        'v1' => V1::class,
        'v2' => V2::class,
    ];
}
```

---

## Recipe 3: CRUD with CrudService

### Step 1: Model + Migration

```php
// app/Models/Product.php
class Product extends Model
{
    protected $fillable = ['name', 'description', 'price', 'status'];
}
```

### Step 2: Create the CrudService

```php
// app/Services/ProductCrudService.php
namespace App\Services;

use App\Models\Product;
use Dskripchenko\LaravelApi\Components\Meta;
use Dskripchenko\LaravelApi\Resources\BaseJsonResource;
use Dskripchenko\LaravelApi\Resources\BaseJsonResourceCollection;
use Dskripchenko\LaravelApi\Services\CrudService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ProductCrudService extends CrudService
{
    public function meta(): Meta
    {
        return (new Meta())
            ->string('name', 'Product name')
            ->string('description', 'Description')
            ->number('price', 'Price')
            ->select('status', 'Status', ['active', 'draft', 'archived'])
            ->crud();
    }

    public function query(): Builder
    {
        return Product::query();
    }

    public function resource(Model $model): BaseJsonResource
    {
        return new BaseJsonResource($model);
    }

    public function collection(Collection $collection): BaseJsonResourceCollection
    {
        return BaseJsonResource::collection($collection);
    }
}
```

### Step 3: Bind in ServiceProvider

```php
use Dskripchenko\LaravelApi\Controllers\CrudController;
use Dskripchenko\LaravelApi\Interfaces\CrudServiceInterface;

// Single CRUD entity — simple bind:
$this->app->bind(CrudServiceInterface::class, ProductCrudService::class);

// Multiple CRUD entities — use contextual binding:
$this->app->when(ProductController::class)
    ->needs(CrudServiceInterface::class)
    ->give(ProductCrudService::class);

$this->app->when(OrderController::class)
    ->needs(CrudServiceInterface::class)
    ->give(OrderCrudService::class);
```

### Step 4: Register in getMethods

```php
'product' => [
    'controller' => CrudController::class,
    'actions' => [
        'meta'   => ['action' => 'meta',   'method' => ['get']],
        'search' => ['action' => 'search', 'method' => ['get', 'post']],
        'create',
        'read'   => ['action' => 'read',   'method' => ['get']],
        'update',
        'delete',
    ],
],
```

### Result

- `GET  /api/v1/product/meta` — field definitions
- `POST /api/v1/product/search` — filtered, sorted, paginated list
- `POST /api/v1/product/create` — create record
- `GET  /api/v1/product/read?id=1` — read record
- `POST /api/v1/product/update` — update record
- `POST /api/v1/product/delete` — delete record

### Search request format

```json
{
  "filter": [
    {"column": "status", "operator": "=", "value": "active"},
    {"column": "price", "operator": "between", "value": [10, 100]},
    {"column": "name", "operator": "like", "value": "phone"},
    {"column": "description", "operator": "is_not_null"}
  ],
  "order": [
    {"column": "price", "value": "desc"}
  ],
  "page": 1,
  "perPage": 20
}
```

---

## Recipe 4: Custom middleware

```php
// app/Http/Middleware/ApiAuthMiddleware.php
namespace App\Http\Middleware;

use Dskripchenko\LaravelApi\Exceptions\ApiException;
use Dskripchenko\LaravelApi\Middlewares\ApiMiddleware;
use Illuminate\Http\Request;
use Closure;

/**
 * @header string $Authorization Bearer token
 */
class ApiAuthMiddleware extends ApiMiddleware
{
    public function run(Request $request, Closure $next)
    {
        if (!$request->bearerToken()) {
            throw new ApiException('unauthorized', 'Bearer token required');
        }

        return $next($request);
    }
}
```

The `@header` tag in middleware docblock is aggregated into OpenAPI documentation.

---

## Recipe 5: OpenAPI with security and templates

```php
class Api extends BaseApi
{
    public static bool $useResponseTemplates = true;

    public static function getOpenApiTemplates(): array
    {
        return [
            'UserResponse' => [
                'id'    => 'integer!',
                'name'  => 'string!',
                'email' => 'string(email)!',
            ],
            'Error' => [
                'errorKey' => 'string!',
                'message'  => 'string!',
            ],
        ];
    }

    public static function getOpenApiSecurityDefinitions(): array
    {
        return [
            'BearerAuth' => [
                'type' => 'apiKey',
                'name' => 'Authorization',
                'in'   => 'header',
            ],
        ];
    }
    // ...
}
```

In controller:
```php
/**
 * Get current user
 *
 * @response 200 {UserResponse}
 * @response 401 {Error}
 * @security BearerAuth
 */
public function me(): JsonResponse { ... }
```

---

## Recipe 6: Write tests with MakesHttpApiRequests

```php
use Dskripchenko\LaravelApi\Traits\Testing\MakesHttpApiRequests;

class UserApiTest extends TestCase
{
    use MakesHttpApiRequests;

    public function test_list_users(): void
    {
        $response = $this->api('v1', 'user', 'list');
        $this->assertApiSuccess($response);
    }

    public function test_create_user_validation(): void
    {
        $response = $this->api('v1', 'user', 'create', []);
        $this->assertApiValidationError($response, ['name', 'email']);
    }

    public function test_show_nonexistent_user(): void
    {
        $response = $this->api('v1', 'user', 'show', ['id' => 99999]);
        $this->assertApiError($response, 'not_found');
    }
}
```

---

## Recipe 7: Custom error handlers

```php
// In your AppServiceProvider or ApiServiceProvider
use Dskripchenko\LaravelApi\Facades\ApiErrorHandler;
use Dskripchenko\LaravelApi\Services\ApiResponseHelper;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

ApiErrorHandler::addErrorHandler(
    ModelNotFoundException::class,
    function (ModelNotFoundException $e) {
        return ApiResponseHelper::sayError([
            'errorKey' => 'not_found',
            'message' => 'Resource not found',
        ], 404);
    }
);

ApiErrorHandler::addErrorHandler(
    AuthenticationException::class,
    function (AuthenticationException $e) {
        return ApiResponseHelper::sayError([
            'errorKey' => 'unauthenticated',
            'message' => 'Authentication required',
        ], 401);
    }
);
```

Handlers support inheritance: a handler for `Exception` will catch `RuntimeException` via `class_parents()` traversal.

---

## Recipe 8: Generate TypeScript interfaces

The `api:generate-types` command generates TypeScript interfaces from your OpenAPI spec.

### Basic usage

```bash
php artisan api:generate-types
```

By default, types are written to `resources/js/shared/api/types.ts`.

### Options

```bash
# Generate for a specific API version
php artisan api:generate-types --version=v1

# Custom output path
php artisan api:generate-types --output=frontend/src/api/types.ts
```

### What gets generated

Given this controller:

```php
class UserController extends ApiController
{
    /**
     * Get user by ID
     *
     * @input integer $id User ID
     *
     * @output integer $id User ID
     * @output string $name User name
     * @output string ?$email User email
     * @output string ?$phone User phone
     */
    public function show(Request $request): JsonResponse
    {
        return $this->success(User::findOrFail($request->input('id'))->toArray());
    }
}
```

The generator produces:

```typescript
export interface UserShowInput {
  id: number;
}

export interface UserShowOutput {
  id: number;
  name: string;
  email?: string;
  phone?: string;
}
```

### Type mapping

| OpenAPI | TypeScript |
|---------|-----------|
| `string` | `string` |
| `integer`, `number` | `number` |
| `boolean` | `boolean` |
| `file`, `string(binary)` | `File` |
| `object` (no properties) | `Record<string, unknown>` |
| `array` + items | `Type[]` |
| `$ref` | Interface name |
| `enum` | `'a' \| 'b' \| 'c'` |

Component schemas from `getOpenApiTemplates()` are generated as named interfaces. Each operation produces `{Controller}{Action}Input` and `{Controller}{Action}Output` types.

---

## Recipe 9: Named routes

All API actions are automatically registered as named Laravel routes. The naming pattern is `api.{version}.{controller}.{action}`.

### Auto-generated names

```php
// getMethods()
'user' => [
    'controller' => UserController::class,
    'actions' => [
        'list' => ['method' => ['get']],  // → api.v1.user.list
        'show' => ['method' => ['get']],  // → api.v1.user.show
        'create',                          // → api.v1.user.create
    ],
],
```

### Custom names

Use the `name` key in action config to override the auto-generated name:

```php
'list' => [
    'action' => 'list',
    'method' => ['get'],
    'name' => 'users.index',             // → api.v1.users.index
],
```

The version prefix `api.{version}.` is always added automatically.

### Using named routes in code

```php
// Generate URL
$url = route('api.v1.user.list');        // → /api/v1/user/list

// In Blade templates
<a href="{{ route('api.v1.user.show') }}">User</a>

// URL facade
$url = URL::route('api.v1.user.list');
```

The catch-all `api-endpoint` route is preserved as a fallback for any requests that don't match a named route.

---

## Recipe 10: Export API in different formats

The `api:export` command converts your OpenAPI spec into ready-to-use formats.

### Postman Collection

```bash
php artisan api:export --format=postman
```

Generates a Postman Collection v2.1 JSON file. Import it in Postman to get all endpoints grouped by controller, with pre-filled parameters, request bodies, and environment variables (`{{baseUrl}}`, `{{token}}`).

### HTTP Client files

```bash
php artisan api:export --format=http
```

Generates `.http` files compatible with JetBrains IDEs (PHPStorm, IntelliJ) and VS Code REST Client extension. Includes `{{host}}` variables and all request body types.

### Markdown documentation

```bash
php artisan api:export --format=markdown
```

Generates standalone Markdown documentation with table of contents, parameter tables, request body fields with required/optional markers, response codes, and deprecated endpoint markers.

### cURL scripts

```bash
php artisan api:export --format=curl
```

Generates a bash script with ready-to-run curl commands. Includes `BASE_URL` and `TOKEN` variables, JSON/form/multipart bodies, and authorization headers for secured endpoints.

### Common options

```bash
# Export specific version only
php artisan api:export --format=postman --version=v1

# Custom output file (all versions merged)
php artisan api:export --format=markdown --output=docs/api.md
```
