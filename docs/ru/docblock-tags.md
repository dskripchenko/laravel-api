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

### Список скаляров

```php
@input array ?$abilities Abilities
@input string $abilities[] One ability
```

`[]` без дочернего поля описывает сам элемент:
`{"type": "array", "items": {"type": "string"}}`.

### Обязательные поля во вложенных структурах

Строка без `?` обязательна на своём уровне: `$widgets[].slug` попадает в
`items.required`, `$address.city` — в `required` объекта `address`, а корневая
строка (`$widgets`, `$address`) — в `required` тела. Корень, известный только
через дочерние поля, ничего не говорит о себе сам и обязательным не помечается.

### Динамические входные данные из метода

```php
@input [getOpenApiMetaInputs]
```

Вызывает метод контроллера и объединяет то, что он вернул, с входными данными
операции. Предназначено для полей, которые известны только во время выполнения —
строятся по модели, по конфигу или по самому маршруту.

**Контекст операции.** Один метод контроллера может обслуживать много маршрутов:
обобщённый CRUD-контроллер регистрируется под своим ключом контроллера для каждой
сущности, а поля зависят от сущности. Поэтому методу сообщается, какая операция
описывается. Через контейнер он получает то из перечисленного, что объявит:

| Параметр | Значение |
|---|---|
| `OperationContext $any` (по типу) | весь контекст, см. ниже |
| `string $version` | версия API, `v1` |
| `string $controllerKey` | ключ контроллера из `getMethods()` |
| `string $actionKey` | ключ действия из `getMethods()` |
| `string $httpMethod` | HTTP-метод в нижнем регистре |

`Dskripchenko\LaravelApi\Services\OpenApi\OperationContext` содержит
`version`, `apiClass` (наследник `BaseApi`), `controllerKey`, `actionKey`,
`controllerClass`, `controllerMethod`, `httpMethod`, `tag` (`input` или
`output`) и `actionOptions` (определение действия из `getMethods()` в том виде,
в каком оно записано), а также `operationId()`.

Метод, который ничего из этого не объявляет, вызывается ровно как раньше, так что
существующие реализации `[method]` продолжают работать без изменений.

**Что возвращает метод.** Одно из двух:

- список строк докблока, историческая форма —
  `['string $name Name', 'integer ?$age Age']`;
- **объект** JSON Schema — массив с `properties` или `type: object`. Он попадает
  в спецификацию как есть, поэтому доступно всё, что не выразить синтаксисом
  строк: `minimum`, `maxLength`, `pattern`, `nullable`, `enum`, вложенные
  `items` и так далее.

Возвращённая схема объединяется с остальными строками `@input` операции — сначала
их свойства, затем свойства схемы, списки `required` объединяются. Для POST
результатом становится JSON-тело запроса; для GET каждое свойство верхнего уровня
становится query-параметром со своей схемой.

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

Теперь спецификация показывает `/v1/users/update` с полями пользователя, а
`/v1/posts/update` — с полями поста, и всё это из одного метода.

---

## @output — Поля ответа

Определяет поля тела ответа для стандартного ответа 200.

### Синтаксис

```
@output type $variableName Description
@output type ?$variableName Необязательное поле ответа
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

### Необязательные поля ответа

Добавьте префикс `?` к имени переменной, чтобы пометить поле ответа как необязательное. Обязательные поля перечисляются в массиве `required` в сгенерированной OpenAPI-схеме.

```php
@output integer $id Обязательное поле     // попадает в "required"
@output string ?$email Необязательное поле // не попадает в "required"
```

---

### Динамические выходные данные из метода

```php
@output integer $id Identifier
@output [entityOutput]
```

То же, что `@input [method]`, только в контексте `tag` равен `output`: метод
возвращает строки или объектную схему, которая накладывается поверх остальных
строк `@output`. Строка `@output {Template}` по-прежнему главнее, а теги
`@response`, если они есть, полностью заменяют `@output`.

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
| `'@Customer!'` | Обязательный `$ref` | `['$ref' => '...']`, поле попадает в `required` |
| `'client!' => [...]` | Обязательное поле с любым определением | `!` на ключе добавляет поле в `required` |
| `['url' => 'string', ...]` | Вложенный объект | `['type' => 'object', 'properties' => [...]]` |
| `['string']` | Массив элементов | `['type' => 'array', 'items' => ['type' => 'string']]` |
| `[['id' => 'integer!']]` | Массив объектов | `['type' => 'array', 'items' => ['type' => 'object', ...]]` |

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

### Вложенные структуры

Массив полей — вложенный объект, список из одного элемента — массив таких элементов, и сокращённая запись работает на любой глубине. Ключ с `!` на конце делает поле обязательным при любом определении — так помечают вложенный объект или массив, у которых нет строки для маркера.

```php
'ProlongationResult' => [
    'uuid'    => 'string(uuid)!',
    'client!' => [                                         // вложенный объект, обязательный
        'email' => 'string(email)',
        'phone' => 'string',
    ],
    'phones'  => ['string'],                               // массив строк
    'lines'   => [['sku' => 'string!', 'qty' => 'integer']], // массив объектов
    'owner'   => '@Customer!',                             // обязательный $ref
],
```

Массив, у которого все ключи — ключевые слова схемы OpenAPI, а `type` называет настоящий тип (`['type' => 'string', 'required' => true]`), считается написанной вручную схемой и проходит как раньше; его `properties` и `items` разворачиваются так же.

---

## Конверт ответа

`ApiResponseHelper::say()` оборачивает каждое JSON-тело — и успех, и ошибку — в `{success, payload}`. По умолчанию спецификация описывает только payload, и клиент видит поля, которые в рантайме лежат на уровень глубже. `$responseEnvelope` на классе Api описывает конверт:

```php
class Api extends BaseApi {
    public static $useResponseTemplates = true;
    public static $responseEnvelope = true;
}
```

Когда он включён:

- каждый ответ со схемой — поля `@output`, `@output {Template}`, `@response 201 {Template}`, `@response 422 {Error}` — становится `{success: boolean, payload: <схема>}`, оба поля обязательные;
- каждый шаблон описывает payload: `'ProlongationAvailable' => ['uuid' => 'string!', ...]`, а не `['success' => ..., 'payload' => [...]]`. Линтер предупреждает о шаблоне, который сам несёт конверт (`template.envelope-duplicated`);
- дефолтные компоненты `Error` и `Success` оборачиваются так же, а собственный шаблон `Error` или `Success` — это payload соответствующего ответа.

Собственный конверт — массив полей в сокращённой записи, где строка `'{payload}'` отмечает место схемы тела:

```php
public static $responseEnvelope = [
    'ok!'   => 'boolean',
    'data!' => '{payload}',
    'meta'  => '@Meta',
];
```

`{payload}` может лежать на любой глубине (`'result' => ['data!' => '{payload}']`). Генератор TypeScript типизирует конверт как обычный объект, и `payload` получает интерфейс шаблона.

Оба свойства в трейте нетипизированы, поэтому переопределение пишется как `public static $responseEnvelope`, без типа: PHP не позволяет типизированно переобъявить нетипизированное статическое свойство.

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
 * @output string ?$notes Примечания
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
