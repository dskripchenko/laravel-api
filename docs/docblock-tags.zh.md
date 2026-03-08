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

### 从方法动态获取输入

```php
@input [getOpenApiMetaInputs]
```

调用控制器上的方法并合并返回的输入参数。

---

## @output — 响应字段

定义默认 200 响应的响应体字段。

### 语法

```
@output type $variableName Description
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
