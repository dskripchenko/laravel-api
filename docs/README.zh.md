# dskripchenko/laravel-api

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE.md)
[![Laravel](https://img.shields.io/badge/Laravel-6.x--12.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net)

🌐 [English](../README.md) | [Русский](README.ru.md) | [Deutsch](README.de.md)

**用于版本化API路由、OpenAPI 3.0自动文档生成和CRUD脚手架的Laravel包。**

使用PHP文档块自动生成OpenAPI文档，构建版本化API——无需维护YAML/JSON模式，无需学习注解库。

## 目录

- [快速开始](#快速开始)
- [功能特性](#功能特性)
- [安装](#安装)
- [架构](#架构)
- [API版本控制](#api版本控制)
- [路由和中间件](#路由和中间件)
- [OpenAPI 3.0文档](#openapi-30文档)
- [CRUD脚手架](#crud脚手架)
- [测试](#测试)
- [配置](#配置)
- [错误处理](#错误处理)
- [与其他方案的对比](#与其他方案的对比)
- [API参考](#api参考)
- [许可证](#许可证)

## 快速开始

```bash
composer require dskripchenko/laravel-api
```

```php
// 1. 定义API类
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

// 2. 定义模块
class ApiModule extends \Dskripchenko\LaravelApi\Components\BaseModule
{
    public function getApiVersionList(): array
    {
        return ['v1' => Api::class];
    }
}

// 3. 定义服务提供者
class ApiServiceProvider extends \Dskripchenko\LaravelApi\Providers\ApiServiceProvider
{
    protected function getApiModule() { return new ApiModule(); }
}

// 4. 编写带文档块的控制器
class UserController extends \Dskripchenko\LaravelApi\Controllers\ApiController
{
    /**
     * 列出用户
     * @input integer ?$page 页码
     * @output integer $id 用户ID
     * @output string $name 用户名
     */
    public function list(Request $request): JsonResponse
    {
        return $this->success(User::paginate()->toArray());
    }
}
```

**结果：**
- `GET /api/v1/user/list` — API端点
- `GET /api/doc` — 自动生成的API文档 (Scalar)

## 功能特性

| 功能 | 描述 |
|------|------|
| **版本化路由** | `api/{version}/{controller}/{action}` 支持版本间继承 |
| **OpenAPI 3.0** | 从`@input`/`@output`文档块自动生成——无需YAML文件 |
| **CRUD脚手架** | 完整的搜索/创建/读取/更新/删除功能，支持筛选、排序、分页 |
| **中间件级联** | 全局→控制器→操作，支持细粒度排除 |
| **响应模板** | `components/schemas`中可重用的`$ref`模式 |
| **安全方案** | `@security`标签 + `securitySchemes`用于Bearer/API密钥认证 |
| **嵌套参数** | 点符号表示法：`@input string $address.city` → 嵌套JSON模式 |
| **文件上传** | `@input file $avatar` → 自动`multipart/form-data` |
| **多重响应** | `@response 200 {Success}` / `@response 422 {Error}` |
| **头部参数** | `@header string $Authorization` —— 聚合来自控制器和中间件 |
| **软删除** | CrudService中内置的`restore()`和`forceDelete()` |
| **请求追踪** | `RequestIdMiddleware` —— `X-Request-Id`传播 + 日志上下文 |
| **可选响应字段** | `@output string ?$email` —— 在OpenAPI schema中将响应字段标记为可选 |
| **TypeScript生成** | `api:generate-types` —— 从OpenAPI规范生成TS接口 |
| **命名路由** | 每个操作注册为命名的 Laravel 路由 — `route('api.v1.user.list')` |
| **测试助手** | `assertApiSuccess()`、`assertApiError()`、`assertApiValidationError()` |
| **可发布配置** | `config/laravel-api.php` —— 前缀、URI模式、HTTP方法 |

## 安装

### 系统要求

- PHP 7.4+
- Laravel 6.x — 12.x

### 安装步骤

```bash
composer require dskripchenko/laravel-api
```

### 发布配置文件

```bash
php artisan vendor:publish --tag=laravel-api-config
```

## 架构

### 请求生命周期

```
HTTP请求
  └─ ApiServiceProvider（注册路由：api/{version}/{controller}/{action}）
      └─ BaseApiRequest（从URI中解析版本、控制器、操作）
          └─ BaseModule::getApi()（版本字符串→BaseApi子类）
              └─ BaseApi::make()
                  ├─ getMethods() → 解析控制器+操作
                  ├─ 中间件级联（全局→控制器→操作）
                  └─ app()->call(Controller@action)
                      └─ JsonResponse {success: true, payload: {...}}
```

### 响应格式

每个响应都包装在标准信封中：

```json
// 成功
{"success": true, "payload": {"id": 1, "name": "John"}}

// 错误
{"success": false, "payload": {"errorKey": "not_found", "message": "用户未找到"}}

// 验证错误
{"success": false, "payload": {"errorKey": "validation", "messages": {"email": ["必填项"]}}}
```

### 目录结构

```
src/
├── Components/     BaseApi, BaseModule, Meta
├── Controllers/    ApiController, CrudController, ApiDocumentationController
├── Exceptions/     ApiException, ApiErrorHandler, Handler
├── Facades/        ApiRequest, ApiModule, ApiErrorHandler
├── Interfaces/     CrudServiceInterface, ApiInterface
├── Middlewares/    ApiMiddleware, RequestIdMiddleware
├── Providers/      ApiServiceProvider, BaseServiceProvider
├── Requests/       BaseApiRequest, CrudSearchRequest
├── Resources/      BaseJsonResource, BaseJsonResourceCollection
├── Services/       ApiResponseHelper, CrudService, OpenApiTypeScriptGenerator
└── Traits/         OpenApiTrait, Testing/MakesHttpApiRequests
```

## API版本控制

API版本使用**PHP类继承** —— 更高版本扩展更低版本：

```php
// V1：完整API
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

// V2：继承V1，选择性修改
class ApiV2 extends ApiV1 {
    public static function getMethods(): array {
        return ['controllers' => [
            'user' => [
                'controller' => UserControllerV2::class,  // 升级的控制器
                'actions' => [
                    'delete' => false,                     // 在v2中删除
                    'archive',                             // 在v2中新增
                ],
            ],
        ]];
    }
}
```

V2自动继承V1中的`list`、`show`、`create`、`update`，同时覆盖控制器并修改操作。

## 路由和中间件

### 操作配置

```php
'actions' => [
    'list',                              // 简单：方法名 = 操作键
    'show' => 'getById',                 // 别名：show → 调用getById()
    'disabled' => false,                 // 禁用操作（404）
    'create' => [
        'action' => 'store',             // 显式方法名
        'method' => ['post'],            // 允许的HTTP方法（默认：['post']）
        'name' => 'orders.store',        // 路由名称：api.{version}.orders.store
        'middleware' => [RateLimit::class],
        'exclude-middleware' => [LogMiddleware::class],
        'exclude-all-middleware' => false,
    ],
]
```

### 中间件级联

```
全局中间件（getMethods根级）
  └─ 控制器中间件
      └─ 操作中间件
```

每个级别都可以使用`exclude-middleware`（特定）或`exclude-all-middleware`（全部）来排除来自父级的中间件。

## OpenAPI 3.0文档

文档从PHP文档块自动生成。无需维护YAML或JSON文件。

### 基本标签

```php
/**
 * 创建订单
 * 端点的详细描述。
 *
 * @input string $title 订单标题
 * @input string ?$notes 可选备注
 * @input integer(int64) $amount 金额（单位：分）
 * @input string $status 状态 [draft,pending,confirmed]
 * @input file ?$attachment 可选文件
 *
 * @output integer $id 创建的订单ID
 * @output string(date-time) $createdAt 时间戳
 * @output string ?$notes 可选备注
 */
```

### 嵌套对象（点符号表示法）

```php
/** @input object $address 地址
 *  @input string $address.city 城市
 *  @input string $address.zip 邮编
 *  @input array $items 订单项目
 *  @input integer $items[].productId 产品
 *  @input integer $items[].quantity 数量 */
```

### 头部、安全性、响应

```php
/**
 * @header string $Authorization Bearer令牌
 * @header string ?$X-Request-Id 跟踪ID
 * @security BearerAuth
 * @response 200 {OrderResponse}
 * @response 422 {ValidationError}
 * @deprecated 使用createV2代替
 */
```

### 响应模板

通过`components/schemas`启用可重用的模式：

```php
class Api extends BaseApi {
    public static bool $useResponseTemplates = true;

    public static function getOpenApiTemplates(): array {
        return [
            'OrderResponse' => [
                'id'         => 'integer!',            // 必填整数
                'title'      => 'string!',             // 必填字符串
                'total'      => 'number',              // 可选数字
                'created_at' => 'string(date-time)',   // 带格式
                'email'      => 'string(email)!',      // 格式+必填
                'customer'   => '@Customer',           // $ref引用其他模式
                'items'      => '@OrderItem[]',        // $ref数组
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

**简写语法：** `type` — 可选，`type!` — 必填，`type(format)` — 带格式，`@Model` — 引用，`@Model[]` — 引用数组。数组格式（`['type' => '...', 'required' => true]`）同样支持。

> 完整标签参考：[docblock-tags.zh.md](docblock-tags.zh.md) | 操作指南：[cookbook.zh.md](cookbook.zh.md)

## CRUD脚手架

实现`CrudService`以获得即时CRUD端点：

```php
class ProductService extends CrudService {
    public function meta(): Meta {
        return (new Meta())
            ->string('name', '名称')
            ->number('price', '价格')
            ->select('status', '状态', ['active', 'draft'])
            ->crud();
    }
    public function query(): Builder { return Product::query(); }
    public function resource(Model $model): BaseJsonResource { return new BaseJsonResource($model); }
    public function collection(Collection $c): BaseJsonResourceCollection { return BaseJsonResource::collection($c); }
}
```

### 支持筛选、排序、分页的搜索

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

**可用操作符：** `=`, `!=`, `>`, `<`, `>=`, `<=`, `in`, `not_in`, `like`, `between`, `is_null`, `is_not_null`

**安全性：** LIKE值自动转义（`%`, `_`, `\`）。所有写操作都包装在`DB::transaction()`中。筛选器数组限制为50个项目。

### 软删除支持

CrudService包括用于使用`SoftDeletes`的模型的`restore($id)`和`forceDelete($id)`。

## 测试

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

## TypeScript 生成

从 OpenAPI 规范生成 TypeScript 接口：

```bash
php artisan api:generate-types                                    # 所有版本 → resources/js/shared/api/types.ts
php artisan api:generate-types --version=v1                       # 指定版本
php artisan api:generate-types --output=frontend/src/api/types.ts # 自定义路径
```

对于 `@output integer $id` 和 `@output string ?$email`，生成器输出：

```typescript
export interface UserShowOutput {
  id: number;
  email?: string;
}
```

组件 schema、操作输入和输出类型均会生成。详情参见 [cookbook.zh.md](cookbook.zh.md#示例-8生成-typescript-接口)。

## 配置

发布配置文件：

```bash
php artisan vendor:publish --tag=laravel-api-config
```

```php
// config/laravel-api.php
return [
    'prefix' => 'api',                                          // URL前缀
    'uri_pattern' => '{version}/{controller}/{action}',          // 路由模式
    'available_methods' => ['get', 'post', 'put', 'patch', 'delete'],
    'openapi_path' => 'public/openapi',                           // OpenAPI JSON输出
    'doc_middleware' => [],                                       // /api/doc的中间件
];
```

## 错误处理

### ApiException

```php
throw new ApiException('payment_failed', '余额不足');
// → {"success": false, "payload": {"errorKey": "payment_failed", "message": "余额不足"}}
```

### 自定义错误处理程序

```php
use Dskripchenko\LaravelApi\Facades\ApiErrorHandler;

ApiErrorHandler::addErrorHandler(
    ModelNotFoundException::class,
    fn($e) => ApiResponseHelper::sayError(['errorKey' => 'not_found', 'message' => '未找到'], 404)
);
```

处理程序支持**继承**：为`Exception`注册处理程序也会通过`class_parents()`遍历捕获`RuntimeException`。

### RequestIdMiddleware

添加到中间件栈以启用请求追踪：

```php
// 从请求头中读取X-Request-Id或生成UUID
// 将request_id添加到Log::shareContext()
// 在响应头中设置X-Request-Id
Dskripchenko\LaravelApi\Middlewares\RequestIdMiddleware::class
```

## 与其他方案的对比

### vs. 经典Laravel方式（手动路由 + FormRequest）

| 方面 | 经典Laravel | laravel-api |
|------|-----------|------------|
| **路由定义** | `routes/api.php` —— 每个端点一条路由，手动版本控制 | `getMethods()` —— 声明式数组，通过类继承进行版本控制 |
| **版本控制** | 手动：路由组、单独控制器、复制粘贴 | 自动：`V2 extends V1`，继承/覆盖/禁用操作 |
| **文档** | 单独流程：手动编写OpenAPI YAML或使用注解 | 从`@input`/`@output`文档块自动生成 |
| **响应格式** | 按控制器随意，无标准信封 | 统一的`{success, payload}`信封 |
| **CRUD样板代码** | 为每个实体编写控制器 + FormRequest + Resource | 实现`CrudService`（4个方法），获得6+个端点 |
| **按操作的中间件** | 路由级中间件或控制器中间件组 | 细粒度：全局→控制器→操作，支持排除 |
| **测试** | `$this->getJson('/api/v1/users')` | `$this->api('v1', 'user', 'list')` + 断言助手 |
| **学习曲线** | 标准Laravel知识 | 学习`getMethods()`结构 + 文档块标签 |
| **灵活性** | 完全控制一切 | 受包约定限制 |
| **何时选择** | 复杂API、非标准路由、GraphQL、事件驱动API | REST API、版本控制、标准CRUD、自动文档需求 |

**laravel-api的优势：**
- 零维护文档 —— 文档块是唯一真实来源
- 版本继承消除API版本间的代码重复
- 所有端点统一的响应格式
- CRUD脚手架减少样板代码60-80%

**laravel-api的劣势：**
- 固定的URI模式（`api/{version}/{controller}/{action}`）—— 非RESTful资源路由
- 独断的响应格式 —— 不能轻易切换到JSON:API或HAL
- 无原生支持资源风格URL（`/users/{id}` vs `/user/show?id=1`）

### vs. L5-Swagger（DarkaOnLine/L5-Swagger）

| 方面 | L5-Swagger | laravel-api |
|------|-----------|------------|
| **方法** | OpenAPI优先：编写注解，生成文档 | 代码优先：编写文档块，文档+路由一起 |
| **注解风格** | 完整OpenAPI注解（`@OA\Get`, `@OA\Schema`, ...） | 轻量级自定义标签（`@input`, `@output`, `@header`） |
| **注解冗长性** | 高：每个端点15-30行完整规范 | 低：每个端点3-10行 |
| **路由** | 无 —— 仅文档，路由单独定义 | 集成 —— 路由+文档来自单个`getMethods()` |
| **版本控制** | 手动 —— 单独注解组 | 内置 —— 类继承 |
| **CRUD生成** | 无 | 内置`CrudService` + `CrudController` |
| **响应格式** | 任意 —— 由你定义模式 | 固定`{success, payload}`信封 |
| **OpenAPI覆盖** | 完整OpenAPI 3.0规范支持 | 子集：覆盖90%常见用例 |
| **IDE支持** | `@OA\*`注解的插件支持 | 无IDE插件 —— 但语法更简单 |
| **生态** | 大型社区，基于swagger-php | 较小，专注型包 |
| **规范自定义** | 完全控制每个OpenAPI字段 | 仅限支持的标签 |
| **何时选择** | API优先设计、需要完全OpenAPI合规、现有路由 | 快速开发、版本化API、集成路由+文档 |

**相比L5-Swagger的优势：**
- 每个端点注解代码少3-5倍
- 路由和文档始终同步（单一真实来源）
- 内置API版本控制和继承
- 包含CRUD脚手架
- 无需学习完整的OpenAPI注解规范

**相比L5-Swagger的劣势：**
- OpenAPI覆盖范围较少（无回调、webhooks、links、discriminator）
- 无自定义标签的IDE插件
- 响应格式固定
- 社区和生态较小
- 不适合API优先（设计优先）工作流

### vs. Scramble（dedoc/scramble）

| 方面 | Scramble | laravel-api |
|------|---------|------------|
| **方法** | 零配置：从代码推断规范（类型、FormRequest、路由） | 文档块标签：显式`@input`/`@output`注解 |
| **路由集成** | 使用Laravel原生路由 | 通过`getMethods()`自定义路由 |
| **文档来源** | PHP类型、FormRequest规则、返回类型 | 文档块注解 |
| **手动注解** | 可选，仅用于边界情况 | 所有端点必需 |
| **版本控制** | 无内置支持 | 内置类继承 |
| **CRUD** | 无 | 内置CrudService |
| **设置工作量** | 最小 —— 安装即可工作 | 中等 —— 定义模块、API类、提供者 |
| **何时选择** | 标准Laravel路由、最小文档工作量 | 自定义路由、版本控制、CRUD需求 |

### 总结：何时使用laravel-api

✅ **在以下情况选择laravel-api：**
- 需要版本间继承的版本化API
- 想要从单一来源集成路由和文档
- 需要支持筛选、排序、分页的CRUD脚手架
- 偏好轻量级文档块标签而非冗长的注解
- 需要所有端点的标准化响应格式

❌ **在以下情况选择其他方案：**
- 需要RESTful资源风格URL（`/users/{id}`）
- 需要完全OpenAPI 3.0合规性（回调、webhooks、discriminator）
- 遵循API优先（设计优先）方法论
- 需要GraphQL或非REST API
- 需要零注解文档（→ Scramble）

## API参考

### 控制器

| 类 | 方法 |
|----|------|
| `ApiController` | `success($payload, $status)`, `error($payload, $status)`, `validationError($messages)`, `created($payload)`, `noContent()`, `notFound($message)` |
| `CrudController` | `meta()`, `search(CrudSearchRequest)`, `create(Request)`, `read(Request, int)`, `update(Request, int)`, `delete(int)` |
| `ApiDocumentationController` | `index()` |

### 服务

| 类 | 方法 |
|----|------|
| `CrudService` | `meta()`, `query()`, `resource()`, `collection()`, `search()`, `create()`, `read()`, `update()`, `delete()`, `restore()`, `forceDelete()` |
| `ApiResponseHelper` | `say($data, $status)`, `sayError($data, $status)` |

### 组件

| 类 | 方法 |
|----|------|
| `BaseApi` | `getMethods()`, `make()`, `getOpenApiTemplates()`, `getOpenApiSecurityDefinitions()`, `beforeCallAction()`, `afterCallAction()`, `getMiddleware()` |
| `BaseModule` | `getApi($version)`, `makeApi()`, `getApiVersionList()`, `getApiPrefix()`, `getApiUriPattern()`, `getAvailableApiMethods()` |
| `Meta` | `string()`, `integer()`, `number()`, `boolean()`, `hidden()`, `select()`, `file()`, `action()`, `crud()`, `getOpenApiInputs()`, `getColumnKeys()` |

### 中间件

| 类 | 目的 |
|----|------|
| `ApiMiddleware` | 抽象基类 —— 捕获`ApiException`和通用异常 |
| `RequestIdMiddleware` | 生成/传播`X-Request-Id`，添加到`Log::shareContext()` |

### 异常

| 类 | 目的 |
|----|------|
| `ApiException` | 异常，带`errorKey`字符串用于结构化错误响应 |
| `ApiErrorHandler` | 按类的异常处理程序注册表，支持父类遍历 |

### Facades

| Facade | 解析为 |
|--------|--------|
| `ApiRequest` | `BaseApiRequest` —— 版本、控制器、操作、HTTP方法 |
| `ApiModule` | `BaseModule` —— 版本解析、路由配置 |
| `ApiErrorHandler` | `ApiErrorHandler` —— 异常处理程序注册表 |

## 许可证

MIT许可证。详见[LICENSE.md](LICENSE.md)。
