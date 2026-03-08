# Cookbook — 分步指南

## 示例 1：从零创建版本化 API

### 步骤 1：创建 API 类

```php
// app/Api/V1/Api.php
namespace App\Api\V1;

use App\Api\V1\Controllers\UserController;
use App\Api\V1\Controllers\OrderController;
use App\Http\Middleware\AuthMiddleware;
use Dskripchenko\LaravelApi\Components\BaseApi;

/**
 * 我的 API v1
 * 应用程序 API 的第一个版本
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

### 步骤 2：创建模块

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

### 步骤 3：创建 ServiceProvider

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

### 步骤 4：创建控制器

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
     * 获取所有用户列表
     * 返回分页的用户列表。
     *
     * @input integer ?$page 页码
     * @input integer ?$perPage 每页条数
     *
     * @output integer $id 用户 ID
     * @output string $name 用户名称
     * @output string(email) $email 用户邮箱
     */
    public function list(Request $request): JsonResponse
    {
        $users = User::paginate($request->input('perPage', 15));
        return $this->success($users->toArray());
    }

    /**
     * 根据 ID 获取用户
     *
     * @input integer $id 用户 ID
     *
     * @output integer $id 用户 ID
     * @output string $name 用户名称
     * @output string(email) $email 用户邮箱
     * @output string(date-time) $createdAt 注册日期
     */
    public function show(Request $request): JsonResponse
    {
        $user = User::findOrFail($request->input('id'));
        return $this->success($user->toArray());
    }

    /**
     * 创建用户
     *
     * @input string $name 用户名称
     * @input string(email) $email 邮箱地址
     * @input string $password 密码
     *
     * @output integer $id 已创建的用户 ID
     */
    public function create(Request $request): JsonResponse
    {
        $user = User::create($request->only(['name', 'email', 'password']));
        return $this->created(['id' => $user->id]);
    }
}
```

### 步骤 5：注册 Provider

```php
// bootstrap/providers.php (Laravel 11+)
return [
    App\Providers\ApiServiceProvider::class,
];
```

### 结果

- `GET  /api/v1/user/list` — 获取用户列表
- `GET  /api/v1/user/show?id=1` — 获取用户详情
- `POST /api/v1/user/create` — 创建用户
- `POST /api/v1/user/update` — 更新用户
- `POST /api/v1/user/delete` — 删除用户
- `POST /api/v1/order/create` — 创建订单
- `GET  /api/doc` — API 文档（Scalar）

---

## 示例 2：添加新的 API 版本

```php
// app/Api/V2/Api.php
namespace App\Api\V2;

use App\Api\V1\Api as V1;
use App\Api\V2\Controllers\UserController;

class Api extends V1  // ← 继承所有 v1 的操作
{
    public static function getMethods(): array
    {
        return [
            'controllers' => [
                'user' => [
                    'controller' => UserController::class, // 新控制器
                    'actions' => [
                        'delete' => false,                 // 在 v2 中禁用删除
                        'archive',                         // 添加新操作
                    ],
                ],
            ],
        ];
    }
}
```

在模块中注册：
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

## 示例 3：使用 CrudService 实现 CRUD

### 步骤 1：模型 + 迁移

```php
// app/Models/Product.php
class Product extends Model
{
    protected $fillable = ['name', 'description', 'price', 'status'];
}
```

### 步骤 2：创建 CrudService

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
            ->string('name', '产品名称')
            ->string('description', '描述')
            ->number('price', '价格')
            ->select('status', '状态', ['active', 'draft', 'archived'])
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

### 步骤 3：在 ServiceProvider 中绑定

```php
use Dskripchenko\LaravelApi\Controllers\CrudController;
use Dskripchenko\LaravelApi\Interfaces\CrudServiceInterface;

// 单个 CRUD 实体 — 简单绑定：
$this->app->bind(CrudServiceInterface::class, ProductCrudService::class);

// 多个 CRUD 实体 — 使用上下文绑定：
$this->app->when(ProductController::class)
    ->needs(CrudServiceInterface::class)
    ->give(ProductCrudService::class);

$this->app->when(OrderController::class)
    ->needs(CrudServiceInterface::class)
    ->give(OrderCrudService::class);
```

### 步骤 4：在 getMethods 中注册

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

### 结果

- `GET  /api/v1/product/meta` — 字段定义
- `POST /api/v1/product/search` — 过滤、排序、分页列表
- `POST /api/v1/product/create` — 创建记录
- `GET  /api/v1/product/read?id=1` — 读取记录
- `POST /api/v1/product/update` — 更新记录
- `POST /api/v1/product/delete` — 删除记录

### 搜索请求格式

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

## 示例 4：自定义中间件

```php
// app/Http/Middleware/ApiAuthMiddleware.php
namespace App\Http\Middleware;

use Dskripchenko\LaravelApi\Exceptions\ApiException;
use Dskripchenko\LaravelApi\Middlewares\ApiMiddleware;
use Illuminate\Http\Request;
use Closure;

/**
 * @header string $Authorization Bearer 令牌
 */
class ApiAuthMiddleware extends ApiMiddleware
{
    public function run(Request $request, Closure $next)
    {
        if (!$request->bearerToken()) {
            throw new ApiException('unauthorized', '需要提供 Bearer 令牌');
        }

        return $next($request);
    }
}
```

中间件文档注释中的 `@header` 标签会被聚合到 Swagger 文档中。

---

## 示例 5：带安全定义和模板的 Swagger

```php
class Api extends BaseApi
{
    public static bool $useResponseTemplates = true;

    public static function getSwaggerTemplates(): array
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

    public static function getSwaggerSecurityDefinitions(): array
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

在控制器中：
```php
/**
 * 获取当前用户
 *
 * @response 200 {UserResponse}
 * @response 401 {Error}
 * @security BearerAuth
 */
public function me(): JsonResponse { ... }
```

---

## 示例 6：使用 MakesHttpApiRequests 编写测试

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

## 示例 7：自定义错误处理器

```php
// 在 AppServiceProvider 或 ApiServiceProvider 中
use Dskripchenko\LaravelApi\Facades\ApiErrorHandler;
use Dskripchenko\LaravelApi\Services\ApiResponseHelper;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

ApiErrorHandler::addErrorHandler(
    ModelNotFoundException::class,
    function (ModelNotFoundException $e) {
        return ApiResponseHelper::sayError([
            'errorKey' => 'not_found',
            'message' => '资源未找到',
        ], 404);
    }
);

ApiErrorHandler::addErrorHandler(
    AuthenticationException::class,
    function (AuthenticationException $e) {
        return ApiResponseHelper::sayError([
            'errorKey' => 'unauthenticated',
            'message' => '需要身份验证',
        ], 401);
    }
);
```

处理器支持继承：为 `Exception` 注册的处理器将通过 `class_parents()` 遍历捕获 `RuntimeException`。
