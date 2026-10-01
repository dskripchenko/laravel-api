# OpenAPI Docblock标签参考

本文档描述了 `OpenApiTrait` 支持的所有 docblock 标签，用于自动生成 OpenAPI 3.0 规范。

## @input — 请求参数

定义请求输入参数。对于 GET 请求，参数放入查询字符串中。对于 POST/PUT/PATCH 请求，参数放入 requestBody 中。

### 语法

```
@input type $variableName Description
@input type ?$variableName Optional parameter
@input type(format) $variableName Type with format
@input type $variableName Description [value1,value2,value3]  ← enum
@input @ModelName Request body as $ref
@input [methodName] Dynamic inputs from a method
```

### 类型

| 类型 | OpenAPI 类型 | 说明 |
|------|-------------|------|
| `string` | `string` | 未知类型的默认回退值 |
| `integer` | `integer` | |
| `number` | `number` | |
| `boolean` | `boolean` | |
| `file` | `string` (format: `binary`) | 触发 `multipart/form-data` 内容类型 |
| `object` | `object` | 与点号表示法配合用于嵌套结构 |
| `array` | `array` | 与 `[]` 表示法配合用于数组元素 |

### 格式

在类型后的括号中指定格式：

```php
@input string(email) $email         // → type: string, format: email
@input string(date-time) $date      // → type: string, format: date-time
@input string(uuid) $id             // → type: string, format: uuid
@input integer(int64) $bigId        // → type: integer, format: int64
@input integer(int32) $count        // → type: integer, format: int32
```

### 枚举

在描述末尾的方括号中指定允许的值：

```php
@input string $status Status [active,blocked,pending]
// → enum: ["active", "blocked", "pending"], description: "Status"
```

### 可选参数

在变量名前加 `?` 前缀：

```php
@input string $name Required field       // required: true
@input string ?$name Optional field      // required: false
```

### 点号表示法（嵌套对象）

```php
@input object $address Address
@input string $address.city City name
@input string $address.zip ZIP code
```

生成嵌套 JSON schema：
```json
{
  "address": {
    "type": "object",
    "properties": {
      "city": {"type": "string"},
      "zip": {"type": "string"}
    }
  }
}
```

### 数组点号表示法

```php
@input array $tags Tags
@input integer $tags[].id Tag ID
@input string $tags[].name Tag name
```

生成：
```json
{
  "tags": {
    "type": "array",
    "items": {
      "type": "object",
      "properties": {
        "id": {"type": "integer"},
        "name": {"type": "string"}
      }
    }
  }
}
```

### 模型引用

```php
@input @OrderCreateRequest
```

在 requestBody 中生成 `$ref: '#/components/schemas/OrderCreateRequest'`。该模型必须在 `getOpenApiTemplates()` 中定义。

### 标量列表

```php
@input array ?$abilities Abilities
@input string $abilities[] One ability
```

不带子字段的 `[]` 描述元素本身：
`{"type": "array", "items": {"type": "string"}}`。

### 嵌套结构中的必填字段

不带 `?` 的行在其所在层级上是必填的：`$widgets[].slug` 进入 `items.required`，
`$address.city` 进入 `address` 的 `required`，根行（`$widgets`、`$address`）进入
请求体的 `required`。仅通过子字段得知的根没有对自身作出任何声明，因此不会被标记
为必填。

### 从方法动态获取输入

```php
@input [getOpenApiMetaInputs]
```

调用控制器上的方法，并将其返回结果合并到操作的输入中。适用于只有在运行时才知道的
字段——根据模型、配置或路由本身构建。

**操作上下文。** 一个控制器方法可能服务于多个路由：通用 CRUD 控制器会按每个实体
各注册一个控制器键，而字段取决于实体。因此会告诉该方法当前描述的是哪个操作。它通过
容器获得自己声明的以下任意参数：

| 参数 | 值 |
|---|---|
| `OperationContext $any`（按类型） | 完整上下文，见下文 |
| `string $version` | API 版本，`v1` |
| `string $controllerKey` | `getMethods()` 中的控制器键 |
| `string $actionKey` | `getMethods()` 中的动作键 |
| `string $httpMethod` | HTTP 方法，小写 |

`Dskripchenko\LaravelApi\Services\OpenApi\OperationContext` 包含
`version`、`apiClass`（`BaseApi` 子类）、`controllerKey`、`actionKey`、
`controllerClass`、`controllerMethod`、`httpMethod`、`tag`（`input` 或
`output`）以及 `actionOptions`（`getMethods()` 中按原样书写的动作定义），另有
`operationId()`。

不声明其中任何参数的方法会像以前一样被调用，因此现有的 `[method]` 实现无需修改
即可继续工作。

**方法返回什么。** 以下两者之一：

- docblock 行的列表，即历史形式——
  `['string $name Name', 'integer ?$age Age']`；
- JSON Schema **对象**——带有 `properties` 或 `type: object` 的数组。它会原样
  写入规范，因此行语法无法表达的一切都可以使用：`minimum`、`maxLength`、
  `pattern`、`nullable`、`enum`、嵌套的 `items` 等等。

返回的 schema 会与该操作的其他 `@input` 行合并——先是它们的属性，然后是 schema
的属性，`required` 列表取并集。对于 POST，结果是 JSON 请求体；对于 GET，每个顶层
属性都会成为带有自身 schema 的查询参数。

```php
use Dskripchenko\LaravelApi\Services\OpenApi\OperationContext;

/**
 * Update an entity
 *
 * @input integer $id Identifier
 * @input [entityFields]
 */
public function update(Request $request): JsonResponse { /* ... */ }

public function entityFields(OperationContext $context): array
{
    $entity = Entities::find($context->controllerKey);

    return [
        'type' => 'object',
        'properties' => $entity->jsonSchemaProperties(),
        'required' => $context->actionKey === 'create' ? $entity->requiredFields() : [],
    ];
}
```

现在规范中 `/v1/users/update` 显示用户的字段，`/v1/posts/update` 显示帖子的字段，
全部出自同一个方法。

---

## @output — 响应字段

定义默认 200 响应的响应体字段。

### 语法

```
@output type $variableName Description
@output type ?$variableName 可选响应字段
@output type(format) $variableName Description
@output @ModelName $field Field as $ref
@output @ModelName[] $field Array of $ref
```

### 示例

```php
@output integer $id Record ID
@output string(date-time) $createdAt Creation date
@output @User $author Author object         // → $ref: '#/components/schemas/User'
@output @User[] $users List of users         // → type: array, items.$ref: '#/components/schemas/User'
@output object $address Address
@output string $address.city City            // nested output
```

### 可选响应字段

在变量名前加 `?` 前缀，将响应字段标记为可选。必填字段会列在生成的 OpenAPI schema 的 `required` 数组中。

```php
@output integer $id 必填字段        // 在 "required" 数组中
@output string ?$email 可选字段     // 不在 "required" 数组中
```

---

### 从方法动态获取输出

```php
@output integer $id Identifier
@output [entityOutput]
```

与 `@input [method]` 相同，只是上下文中的 `tag` 为 `output`：方法返回行或对象
schema，合并到其他 `@output` 行之上。`@output {Template}` 行仍然优先，而存在
`@response` 标签时，它们会完全取代 `@output`。

---

## @header — 请求头

定义操作的请求头参数。

### 语法

```
@header type $HeaderName Description
@header type ?$HeaderName Optional header
```

### 示例

```php
@header string $Authorization Bearer token
@header string ?$X-Request-Id Optional trace ID
```

请求头也可以在中间件的 docblock 中定义——它们会从控制器方法和中间件链中的所有中间件聚合而来。

---

## @response — 多个 HTTP 响应

定义带有特定 HTTP 状态码的响应。当存在时，将覆盖 `@output` 生成的默认 200 响应。

### 语法

```
@response CODE {TemplateName}
@response CODE Description text
```

### 示例

```php
@response 200 {UserResponse}        // → $ref to component schema
@response 422 {ValidationError}     // → $ref to component schema
@response 404 Not found             // → description only
```

如果没有 `@response` 标签，则使用 `@output` 来构建 200 响应。

---

## @security — 操作安全

为操作应用安全方案。

### 语法

```
@security SchemeName
```

### 示例

```php
@security BearerAuth
```

该方案必须在 Api 类的 `getOpenApiSecurityDefinitions()` 中定义：

```php
public static function getOpenApiSecurityDefinitions(): array {
    return [
        'BearerAuth' => [
            'type' => 'apiKey',
            'name' => 'Authorization',
            'in' => 'header',
        ],
    ];
}
```

---

## @deprecated — 标记为已弃用

在 OpenAPI 规范中将操作标记为已弃用。

### 语法

```
@deprecated Optional explanation
```

---

## @default — 默认值

为参数设置默认值。

### 语法

```
@default $variableName value
```

### 示例

```php
@input integer ?$page Page number
@default $page 1
```

---

## @example — 示例值

为参数设置示例值。

### 语法

```
@example $variableName value
```

### 示例

```php
@input integer ?$page Page number
@example $page 3
```

---

## 模板简写语法

在 `getOpenApiTemplates()` 中定义模板时，可以使用简写字符串表示法代替冗长的数组：

| 语法 | 含义 | 等效数组 |
|--------|---------|------------------|
| `'integer'` | 可选整数 | `['type' => 'integer']` |
| `'string!'` | 必填字符串 | `['type' => 'string', 'required' => true]` |
| `'string(email)'` | 带格式的字符串 | `['type' => 'string', 'format' => 'email']` |
| `'string(date-time)!'` | 格式 + 必填 | `['type' => 'string', 'format' => 'date-time', 'required' => true]` |
| `'@Customer'` | `$ref` 到 schema | `['$ref' => '#/components/schemas/Customer']` |
| `'@OrderItem[]'` | `$ref` 数组 | `['type' => 'array', 'items' => ['$ref' => '...']]` |
| `'@Customer!'` | 必填 `$ref` | `['$ref' => '...']`，并列入 `required` |
| `'client!' => [...]` | 任意定义的必填字段 | 键上的 `!` 把字段加入 `required` |
| `['url' => 'string', ...]` | 嵌套对象 | `['type' => 'object', 'properties' => [...]]` |
| `['string']` | 元素数组 | `['type' => 'array', 'items' => ['type' => 'string']]` |
| `[['id' => 'integer!']]` | 对象数组 | `['type' => 'array', 'items' => ['type' => 'object', ...]]` |

### 示例

```php
public static function getOpenApiTemplates(): array {
    return [
        'OrderResponse' => [
            'id'         => 'integer!',
            'title'      => 'string!',
            'total'      => 'number',
            'created_at' => 'string(date-time)',
            'email'      => 'string(email)!',
            'customer'   => '@Customer',
            'items'      => '@OrderItem[]',
        ],
    ];
}
```

两种格式可以在同一个模板中混合使用。数组格式（`['type' => '...', 'required' => true]`）仍然完全支持。

### 嵌套结构

字段映射是嵌套对象，单元素列表是该元素的数组，简写在任意深度都有效。以 `!` 结尾的键使字段成为必填，无论其定义如何——嵌套对象和数组没有字符串来携带标记，就用这种方式。

```php
'ProlongationResult' => [
    'uuid'    => 'string(uuid)!',
    'client!' => [                                         // 嵌套对象，必填
        'email' => 'string(email)',
        'phone' => 'string',
    ],
    'phones'  => ['string'],                               // 字符串数组
    'lines'   => [['sku' => 'string!', 'qty' => 'integer']], // 对象数组
    'owner'   => '@Customer!',                             // 必填 $ref
],
```

如果一个映射的所有键都是 OpenAPI schema 关键字，且 `type` 是真实类型（`['type' => 'string', 'required' => true]`），它被视为手写 schema 并照旧原样传递，其 `properties` 和 `items` 以同样方式展开。

---

## 响应信封

`ApiResponseHelper::say()` 把每个 JSON 响应体——成功与错误一样——包在 `{success, payload}` 里。默认情况下规范只描述 payload，客户端看到的字段在运行时其实深一层。Api 类上的 `$responseEnvelope` 用于描述信封：

```php
class Api extends BaseApi {
    public static $useResponseTemplates = true;
    public static $responseEnvelope = true;
}
```

开启后：

- 每个带 schema 的响应——`@output` 字段、`@output {Template}`、`@response 201 {Template}`、`@response 422 {Error}`——都变为 `{success: boolean, payload: <schema>}`，两个字段均为必填；
- 每个模板描述的是 payload：`'ProlongationAvailable' => ['uuid' => 'string!', ...]`，而不是 `['success' => ..., 'payload' => [...]]`。对于自带信封的模板，linter 会发出警告（`template.envelope-duplicated`）；
- 默认的 `Error` 和 `Success` 组件以同样方式包装，自定义的 `Error` 或 `Success` 模板即为该响应的 payload。

自定义信封是简写形式的字段映射，字符串 `'{payload}'` 标记响应体 schema 的位置：

```php
public static $responseEnvelope = [
    'ok!'   => 'boolean',
    'data!' => '{payload}',
    'meta'  => '@Meta',
];
```

`{payload}` 可以位于任意深度（`'result' => ['data!' => '{payload}']`）。TypeScript 生成器把信封当作普通对象处理，`payload` 得到模板的接口类型。

这两个属性在 trait 中都未声明类型，因此覆盖时写 `public static $responseEnvelope`，不带类型：PHP 不允许对未类型化的静态属性进行带类型的重新声明。

---

## 内容类型自动检测

POST requestBody 的内容类型会自动确定：

| 条件 | Content-Type |
|-----------|-------------|
| 包含 `file` 类型输入 | `multipart/form-data` |
| 包含点号表示法（嵌套）输入 | `application/json` |
| 包含模型引用（`@ModelName`） | `application/json` |
| 仅有扁平输入 | `application/x-www-form-urlencoded` |

---

## 完整示例

```php
/**
 * Create a new order
 * Creates an order with the specified items and shipping address.
 *
 * @input string $title Order title
 * @input string $status Status [draft,pending,confirmed]
 * @input string(email) $email Contact email
 * @input object $address Shipping address
 * @input string $address.city City
 * @input string $address.zip ZIP code
 * @input array $items Order items
 * @input integer $items[].productId Product ID
 * @input integer $items[].quantity Quantity
 * @input file ?$attachment Optional attachment
 *
 * @output integer $id Order ID
 * @output string(date-time) $createdAt Creation timestamp
 * @output string ?$notes 可选备注
 * @output @User $createdBy Creator
 *
 * @header string $Authorization Bearer token
 * @security BearerAuth
 *
 * @response 201 {OrderResponse}
 * @response 422 {ValidationError}
 *
 * @default $status draft
 * @example $title "Summer sale order"
 */
public function create(Request $request): JsonResponse
```
