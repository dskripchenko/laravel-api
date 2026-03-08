# Справочник OpenAPI Docblock-тегов

Этот документ описывает все docblock-теги, поддерживаемые `OpenApiTrait` для автоматической генерации OpenAPI 3.0.

## @input — Параметры запроса

Определяет входные параметры запроса. Для GET-запросов параметры помещаются в строку запроса (query string). Для POST/PUT/PATCH — в тело запроса (requestBody).

### Синтаксис

```
@input type $variableName Description
@input type ?$variableName Optional parameter
@input type(format) $variableName Type with format
@input type $variableName Description [value1,value2,value3]  ← enum
@input @ModelName Request body as $ref
@input [methodName] Dynamic inputs from a method
```

### Типы

| Тип | Тип OpenAPI | Примечания |
|------|-------------|-------|
| `string` | `string` | Тип по умолчанию для неизвестных типов |
| `integer` | `integer` | |
| `number` | `number` | |
| `boolean` | `boolean` | |
| `file` | `string` (format: `binary`) | Активирует тип содержимого `multipart/form-data` |
| `object` | `object` | Используется с точечной нотацией для вложенных структур |
| `array` | `array` | Используется с нотацией `[]` для элементов массива |

### Формат

Формат указывается в скобках после типа:

```php
@input string(email) $email         // → type: string, format: email
@input string(date-time) $date      // → type: string, format: date-time
@input string(uuid) $id             // → type: string, format: uuid
@input integer(int64) $bigId        // → type: integer, format: int64
@input integer(int32) $count        // → type: integer, format: int32
```

### Перечисление (Enum)

Допустимые значения указываются в квадратных скобках в конце описания:

```php
@input string $status Status [active,blocked,pending]
// → enum: ["active", "blocked", "pending"], description: "Status"
```

### Необязательные параметры

Добавьте префикс `?` к имени переменной:

```php
@input string $name Required field       // required: true
@input string ?$name Optional field      // required: false
```

### Точечная нотация (вложенные объекты)

```php
@input object $address Address
@input string $address.city City name
@input string $address.zip ZIP code
```

Генерирует вложенную JSON-схему:
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

### Точечная нотация для массивов

```php
@input array $tags Tags
@input integer $tags[].id Tag ID
@input string $tags[].name Tag name
```

Генерирует:
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

### Ссылка на модель

```php
@input @OrderCreateRequest
```

Генерирует `$ref: '#/components/schemas/OrderCreateRequest'` в requestBody. Модель должна быть определена в `getOpenApiTemplates()`.

### Динамические входные данные из метода

```php
@input [getOpenApiMetaInputs]
```

Вызывает метод контроллера и объединяет возвращённые входные данные.

---

## @output — Поля ответа

Определяет поля тела ответа для стандартного ответа 200.

### Синтаксис

```
@output type $variableName Description
@output type(format) $variableName Description
@output @ModelName $field Field as $ref
@output @ModelName[] $field Array of $ref
```

### Примеры

```php
@output integer $id Record ID
@output string(date-time) $createdAt Creation date
@output @User $author Author object         // → $ref: '#/components/schemas/User'
@output @User[] $users List of users         // → type: array, items.$ref: '#/components/schemas/User'
@output object $address Address
@output string $address.city City            // nested output
```

---

## @header — Заголовки запроса

Определяет параметры заголовков для операции.

### Синтаксис

```
@header type $HeaderName Description
@header type ?$HeaderName Optional header
```

### Примеры

```php
@header string $Authorization Bearer token
@header string ?$X-Request-Id Optional trace ID
```

Заголовки также могут быть определены в docblock-ах middleware — они агрегируются как из метода контроллера, так и из всех middleware в цепочке.

---

## @response — Множественные HTTP-ответы

Определяет ответы с конкретными HTTP-кодами состояния. При наличии переопределяет стандартный ответ 200 из `@output`.

### Синтаксис

```
@response CODE {TemplateName}
@response CODE Description text
```

### Примеры

```php
@response 200 {UserResponse}        // → $ref к схеме компонента
@response 422 {ValidationError}     // → $ref к схеме компонента
@response 404 Not found             // → только описание
```

Если теги `@response` отсутствуют, для построения ответа 200 используется `@output`.

---

## @security — Безопасность операции

Применяет схему безопасности к операции.

### Синтаксис

```
@security SchemeName
```

### Пример

```php
@security BearerAuth
```

Схема должна быть определена в `getOpenApiSecurityDefinitions()` класса Api:

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

## @deprecated — Пометить как устаревшее

Помечает операцию как устаревшую в спецификации OpenAPI.

### Синтаксис

```
@deprecated Optional explanation
```

---

## @default — Значение по умолчанию

Устанавливает значение по умолчанию для параметра.

### Синтаксис

```
@default $variableName value
```

### Пример

```php
@input integer ?$page Page number
@default $page 1
```

---

## @example — Пример значения

Устанавливает пример значения для параметра.

### Синтаксис

```
@example $variableName value
```

### Пример

```php
@input integer ?$page Page number
@example $page 3
```

---

## Сокращённый синтаксис шаблонов

При определении шаблонов в `getOpenApiTemplates()` можно использовать сокращённую строковую нотацию вместо подробных массивов:

| Синтаксис | Значение | Эквивалентный массив |
|--------|---------|------------------|
| `'integer'` | Необязательное целое число | `['type' => 'integer']` |
| `'string!'` | Обязательная строка | `['type' => 'string', 'required' => true]` |
| `'string(email)'` | Строка с форматом | `['type' => 'string', 'format' => 'email']` |
| `'string(date-time)!'` | Формат + обязательное | `['type' => 'string', 'format' => 'date-time', 'required' => true]` |
| `'@Customer'` | `$ref` на схему | `['$ref' => '#/components/schemas/Customer']` |
| `'@OrderItem[]'` | Массив `$ref` | `['type' => 'array', 'items' => ['$ref' => '...']]` |

### Пример

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

Оба формата можно комбинировать в одном шаблоне. Формат массива (`['type' => '...', 'required' => true]`) по-прежнему полностью поддерживается.

---

## Автоопределение Content-type

Тип содержимого для POST requestBody определяется автоматически:

| Условие | Content-Type |
|-----------|-------------|
| Есть входной параметр типа `file` | `multipart/form-data` |
| Есть входные данные с точечной нотацией (вложенные) | `application/json` |
| Есть ссылка на модель (`@ModelName`) | `application/json` |
| Только плоские входные данные | `application/x-www-form-urlencoded` |

---

## Полный пример

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
