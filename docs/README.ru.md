# dskripchenko/laravel-api

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE.md)
[![Laravel](https://img.shields.io/badge/Laravel-6.x--12.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net)

🌐 [English](../README.md) | [Deutsch](README.de.md) | [中文](README.zh.md)

**Laravel-пакет для версионированного API-маршрутизирования, автоматической генерации OpenAPI 3.0 документации и CRUD-скаффолдинга.**

Создавайте версионированные API с автоматически генерируемой OpenAPI-документацией из PHP-docblock'ов — без YAML/JSON-схем и сложных библиотек аннотаций.

## Содержание

- [Быстрый старт](#быстрый-старт)
- [Возможности](#возможности)
- [Установка](#установка)
- [Архитектура](#архитектура)
- [Версионирование API](#версионирование-api)
- [Маршрутизация и middleware](#маршрутизация-и-middleware)
- [OpenAPI 3.0 документация](#openapi-30-документация)
- [CRUD скаффолдинг](#crud-скаффолдинг)
- [Тестирование](#тестирование)
- [Конфигурация](#конфигурация)
- [Обработка ошибок](#обработка-ошибок)
- [Сравнение с альтернативами](#сравнение-с-альтернативами)
- [Справочник API](#справочник-api)
- [Лицензия](#лицензия)

## Быстрый старт

```bash
composer require dskripchenko/laravel-api
```

```php
// 1. Определите класс вашего API
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

// 2. Определите модуль
class ApiModule extends \Dskripchenko\LaravelApi\Components\BaseModule
{
    public function getApiVersionList(): array
    {
        return ['v1' => Api::class];
    }
}

// 3. Определите ServiceProvider
class ApiServiceProvider extends \Dskripchenko\LaravelApi\Providers\ApiServiceProvider
{
    protected function getApiModule() { return new ApiModule(); }
}

// 4. Напишите контроллер с docblock'ами
class UserController extends \Dskripchenko\LaravelApi\Controllers\ApiController
{
    /**
     * Список пользователей
     * @input integer ?$page Номер страницы
     * @output integer $id ID пользователя
     * @output string $name Имя пользователя
     */
    public function list(Request $request): JsonResponse
    {
        return $this->success(User::paginate()->toArray());
    }
}
```

**Результат:**
- `GET /api/v1/user/list` — API endpoint
- `GET /api/doc` — автоматически сгенерированная документация API (Scalar)

## Возможности

| Возможность | Описание |
|---------|-------------|
| **Версионированная маршрутизация** | `api/{version}/{controller}/{action}` с наследованием между версиями |
| **OpenAPI 3.0** | Автоматическая генерация из `@input`/`@output` docblock'ов — без YAML-файлов |
| **CRUD скаффолдинг** | Полная реализация поиска/создания/чтения/обновления/удаления с фильтрацией, сортировкой, пагинацией |
| **Cascade middleware** | Глобальный → контроллер → action с гибким исключением |
| **Шаблоны ответов** | Переиспользуемые `$ref` схемы в `components/schemas` |
| **Схемы безопасности** | `@security` тег + `securitySchemes` для Bearer/API key |
| **Вложенные параметры** | Dot-нотация: `@input string $address.city` → вложенная JSON-схема |
| **Загрузка файлов** | `@input file $avatar` → автоматический `multipart/form-data` |
| **Несколько ответов** | `@response 200 {Success}` / `@response 422 {Error}` |
| **Параметры заголовков** | `@header string $Authorization` — агрегация из контроллера и middleware |
| **Soft deletes** | Встроенные `restore()` и `forceDelete()` в CrudService |
| **Трассировка запросов** | `RequestIdMiddleware` — распространение `X-Request-Id` + контекст логов |
| **Необязательные поля ответа** | `@output string ?$email` — помечает поля ответа как необязательные в OpenAPI-схеме |
| **Генерация TypeScript** | `api:generate-types` — генерирует TS-интерфейсы из OpenAPI-спецификации |
| **Именованные маршруты** | Каждый action регистрируется как именованный Laravel-маршрут — `route('api.v1.user.list')` |
| **Экспорт API** | `api:export` — Postman Collection, HTTP Client (.http), Markdown, cURL |
| **Помощники тестирования** | `assertApiSuccess()`, `assertApiError()`, `assertApiValidationError()` |
| **Публикуемая конфигурация** | `config/laravel-api.php` — префикс, шаблон URI, HTTP методы |

## Установка

### Требования

- PHP 7.4+
- Laravel 6.x — 12.x

### Установка пакета

```bash
composer require dskripchenko/laravel-api
```

### Публикация конфигурации

```bash
php artisan vendor:publish --tag=laravel-api-config
```

## Архитектура

### Жизненный цикл запроса

```
HTTP Request
  └─ ApiServiceProvider (регистрирует маршрут: api/{version}/{controller}/{action})
      └─ BaseApiRequest (парсит версию, контроллер, action из URI)
          └─ BaseModule::getApi() (строка версии → подкласс BaseApi)
              └─ BaseApi::make()
                  ├─ getMethods() → разрешает контроллер + action
                  ├─ Cascade middleware (глобальный → контроллер → action)
                  └─ app()->call(Controller@action)
                      └─ JsonResponse {success: true, payload: {...}}
```

### Формат ответа

Каждый ответ обёрнут в стандартный конверт:

```json
// Успех
{"success": true, "payload": {"id": 1, "name": "John"}}

// Ошибка
{"success": false, "payload": {"errorKey": "not_found", "message": "User not found"}}

// Ошибка валидации
{"success": false, "payload": {"errorKey": "validation", "messages": {"email": ["Required"]}}}
```

### Структура каталогов

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

## Версионирование API

Версии API используют **наследование PHP-классов** — более поздние версии расширяют более ранние:

```php
// V1: полный API
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

// V2: наследует V1, модифицирует выборочно
class ApiV2 extends ApiV1 {
    public static function getMethods(): array {
        return ['controllers' => [
            'user' => [
                'controller' => UserControllerV2::class,  // обновлённый контроллер
                'actions' => [
                    'delete' => false,                     // удалено в v2
                    'archive',                             // новое в v2
                ],
            ],
        ]];
    }
}
```

V2 автоматически наследует `list`, `show`, `create`, `update` из V1, переопределяя контроллер и модифицируя actions.

## Маршрутизация и middleware

### Конфигурация action'а

```php
'actions' => [
    'list',                              // простой: имя метода = имя action'а
    'show' => 'getById',                 // алиас: show → вызывает getById()
    'disabled' => false,                 // отключённый action (404)
    'create' => [
        'action' => 'store',             // явное имя метода
        'method' => ['post'],            // разрешённые HTTP методы (по умолчанию: ['post'])
        'name' => 'orders.store',        // имя маршрута: api.{version}.orders.store
        'middleware' => [RateLimit::class],
        'exclude-middleware' => [LogMiddleware::class],
        'exclude-all-middleware' => false,
    ],
]
```

### Cascade middleware

```
Глобальный middleware (корень getMethods)
  └─ Middleware контроллера
      └─ Middleware action'а
```

На каждом уровне можно исключить middleware из верхних уровней, используя `exclude-middleware` (специфический) или `exclude-all-middleware` (все).

## OpenAPI 3.0 документация

Документация генерируется автоматически из PHP docblock'ов. Нет необходимости в YAML или JSON файлах.

### Основные теги

```php
/**
 * Создать заказ
 * Подробное описание endpoint'а.
 *
 * @input string $title Название заказа
 * @input string ?$notes Опциональные заметки
 * @input integer(int64) $amount Сумма в копейках
 * @input string $status Статус [draft,pending,confirmed]
 * @input file ?$attachment Опциональный файл
 *
 * @output integer $id ID созданного заказа
 * @output string(date-time) $createdAt Временная метка
 * @output string ?$notes Примечания
 */
```

### Вложенные объекты (dot-нотация)

```php
/** @input object $address Адрес
 *  @input string $address.city Город
 *  @input string $address.zip Почтовый индекс
 *  @input array $items Товары в заказе
 *  @input integer $items[].productId Товар
 *  @input integer $items[].quantity Количество */
```

### Заголовки, безопасность, ответы

```php
/**
 * @header string $Authorization Bearer токен
 * @header string ?$X-Request-Id ID трассировки
 * @security BearerAuth
 * @response 200 {OrderResponse}
 * @response 422 {ValidationError}
 * @deprecated Используйте createV2
 */
```

### Шаблоны ответов

Включите переиспользуемые схемы через `components/schemas`:

```php
class Api extends BaseApi {
    public static bool $useResponseTemplates = true;

    public static function getOpenApiTemplates(): array {
        return [
            'OrderResponse' => [
                'id'         => 'integer!',            // обязательное целое
                'title'      => 'string!',             // обязательная строка
                'total'      => 'number',              // необязательное число
                'created_at' => 'string(date-time)',   // с форматом
                'email'      => 'string(email)!',      // формат + обязательное
                'customer'   => '@Customer',           // $ref на другую схему
                'items'      => '@OrderItem[]',        // массив $ref
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

**Shorthand-синтаксис:** `type` — необязательное, `type!` — обязательное, `type(format)` — с форматом, `@Model` — ссылка, `@Model[]` — массив ссылок. Также поддерживается формат массивов (`['type' => '...', 'required' => true]`).

> Полный справочник тегов: [docblock-tags.ru.md](docblock-tags.ru.md) | Рецепты: [cookbook.ru.md](cookbook.ru.md)

## CRUD скаффолдинг

Реализуйте `CrudService` для мгновенных CRUD endpoint'ов:

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

### Поиск с фильтрацией, сортировкой, пагинацией

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

**Доступные операторы:** `=`, `!=`, `>`, `<`, `>=`, `<=`, `in`, `not_in`, `like`, `between`, `is_null`, `is_not_null`

**Безопасность:** значения LIKE автоматически экранируются (`%`, `_`, `\`). Все операции записи обёрнуты в `DB::transaction()`. Массив фильтров ограничен 50 элементами.

### Поддержка soft delete

CrudService включает `restore($id)` и `forceDelete($id)` для моделей с `SoftDeletes`.

## Тестирование

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

## Генерация TypeScript

Генерация TypeScript-интерфейсов из OpenAPI-спецификации:

```bash
php artisan api:generate-types                                    # Все версии → resources/js/shared/api/types.ts
php artisan api:generate-types --version=v1                       # Конкретная версия
php artisan api:generate-types --output=frontend/src/api/types.ts # Свой путь
```

Для `@output integer $id` и `@output string ?$email` генератор создаёт:

```typescript
export interface UserShowOutput {
  id: number;
  email?: string;
}
```

Генерируются схемы компонентов, входные и выходные типы операций. Подробности в [cookbook.ru.md](cookbook.ru.md#рецепт-8-генерация-typescript-интерфейсов).

## Экспорт API

Экспорт спецификации API в различных форматах:

```bash
php artisan api:export --format=postman    # Postman Collection v2.1
php artisan api:export --format=http       # .http файлы для PHPStorm/VS Code
php artisan api:export --format=markdown   # Автономная документация
php artisan api:export --format=curl       # Bash-скрипт с curl командами
```

Опции: `--version=v1` (конкретная версия), `--output=path` (свой путь). По умолчанию генерирует файлы по версиям (`v1.json`, `v1.http`, `v1.md`, `v1.sh`).

## Конфигурация

Опубликуйте файл конфигурации:

```bash
php artisan vendor:publish --tag=laravel-api-config
```

```php
// config/laravel-api.php
return [
    'prefix' => 'api',                                          // Префикс URL
    'uri_pattern' => '{version}/{controller}/{action}',          // Шаблон маршрута
    'available_methods' => ['get', 'post', 'put', 'patch', 'delete'],
    'openapi_path' => 'public/openapi',                           // Выходной путь OpenAPI JSON
    'doc_middleware' => [],                                       // Middleware для /api/doc
];
```

## Обработка ошибок

### ApiException

```php
throw new ApiException('payment_failed', 'Insufficient funds');
// → {"success": false, "payload": {"errorKey": "payment_failed", "message": "Insufficient funds"}}
```

### Пользовательские обработчики ошибок

```php
use Dskripchenko\LaravelApi\Facades\ApiErrorHandler;

ApiErrorHandler::addErrorHandler(
    ModelNotFoundException::class,
    fn($e) => ApiResponseHelper::sayError(['errorKey' => 'not_found', 'message' => 'Not found'], 404)
);
```

Обработчики поддерживают **наследование**: регистрация обработчика для `Exception` также поймает `RuntimeException` путём обхода `class_parents()`.

### RequestIdMiddleware

Добавьте в ваш middleware stack для трассировки запросов:

```php
// Читает X-Request-Id из заголовка запроса или генерирует UUID
// Добавляет request_id в Log::shareContext()
// Устанавливает X-Request-Id в заголовок ответа
Dskripchenko\LaravelApi\Middlewares\RequestIdMiddleware::class
```

## Сравнение с альтернативами

### vs. Классический подход Laravel (ручные маршруты + FormRequest)

| Аспект | Классический Laravel | laravel-api |
|--------|------------------|-------------|
| **Определение маршрутов** | `routes/api.php` — один маршрут на endpoint, ручное версионирование | `getMethods()` — декларативный массив, версии через наследование классов |
| **Версионирование** | Ручное: группы маршрутов, отдельные контроллеры, copy-paste | Автоматическое: `V2 extends V1`, наследование/переопределение/отключение actions |
| **Документация** | Отдельный процесс: ручное написание OpenAPI YAML или использование аннотаций | Автогенерация из `@input`/`@output` docblock'ов |
| **Формат ответа** | Ad-hoc на контроллер, без стандартного конверта | Стандартизированный `{success, payload}` конверт везде |
| **CRUD boilerplate** | Написать контроллер + FormRequest + Resource для каждой сущности | Реализовать `CrudService` (4 метода), получить 6+ endpoint'ов |
| **Middleware по action'у** | Middleware на уровне маршрута или группы контроллеров | Гранулярный: глобальный → контроллер → action с исключением |
| **Тестирование** | `$this->getJson('/api/v1/users')` | `$this->api('v1', 'user', 'list')` + assertion helpers |
| **Кривая обучения** | Стандартные знания Laravel | Изучить структуру `getMethods()` + docblock теги |
| **Гибкость** | Полный контроль над всем | Ограничено соглашениями пакета |
| **Когда использовать** | Сложные API с нестандартной маршрутизацией, GraphQL, event-driven API | REST API с версионированием, стандартный CRUD, нужна автодокументация |

**Преимущества laravel-api:**
- Zero-maintenance документация — docblock'и — единственный источник истины
- Наследование версий исключает дублирование кода между версиями API
- Стандартизированный формат ответа по всем endpoint'ам
- CRUD скаффолдинг сокращает boilerplate на 60-80%

**Недостатки laravel-api:**
- Фиксированный URI паттерн (`api/{version}/{controller}/{action}`) — не RESTful resource routes
- Opinionated формат ответа — нелегко переключиться на JSON:API или HAL
- Нет встроенной поддержки resource-style URLs (`/users/{id}` vs `/user/show?id=1`)

### vs. L5-Swagger (DarkaOnLine/L5-Swagger)

| Аспект | L5-Swagger | laravel-api |
|--------|-----------|-------------|
| **Подход** | OpenAPI-first: напишите аннотации, генерируйте документацию | Code-first: напишите docblock'и, документация + маршрутизация вместе |
| **Стиль аннотаций** | Полные OpenAPI аннотации (`@OA\Get`, `@OA\Schema`, ...) | Лёгкие пользовательские теги (`@input`, `@output`, `@header`) |
| **Многословность аннотаций** | Высокая: 15-30 строк на endpoint для полной спецификации | Низкая: 3-10 строк на endpoint |
| **Маршрутизация** | Отсутствует — только документация, маршруты определяются отдельно | Интегрированная — маршрутизация + документация из одного `getMethods()` |
| **Версионирование** | Ручное — отдельные группы аннотаций | Встроенное — наследование классов |
| **Генерация CRUD** | Отсутствует | Встроенный `CrudService` + `CrudController` |
| **Формат ответа** | Любой — вы определяете схемы | Фиксированный `{success, payload}` конверт |
| **Покрытие OpenAPI** | Полная поддержка OpenAPI 3.0 spec | Подмножество: охватывает 90% типичных случаев |
| **Поддержка IDE** | Plugin support для `@OA\*` аннотаций | Нет IDE plugin — но проще синтаксис |
| **Экосистема** | Большое сообщество, swagger-php под капотом | Компактный, сфокусированный пакет |
| **Кастомизация спека** | Полный контроль над каждым OpenAPI полем | Ограничено поддерживаемыми тегами |
| **Когда использовать** | API-first дизайн, нужна полная OpenAPI совместимость, существующие маршруты | Быстрая разработка, версионированные API, интегрированная маршрутизация + документация |

**Преимущества перед L5-Swagger:**
- В 3-5 раз меньше кода аннотаций на endpoint
- Маршрутизация и документация всегда синхронизированы (единственный источник)
- Встроенное версионирование API с наследованием
- Включён CRUD скаффолдинг
- Не нужно учить полную OpenAPI спецификацию аннотаций

**Недостатки по сравнению с L5-Swagger:**
- Меньше покрытие OpenAPI (нет callbacks, webhooks, links, discriminator)
- Нет IDE plugin для пользовательских тегов
- Фиксированный формат ответа
- Меньшее сообщество и экосистема
- Не подходит для API-first (design-first) workflow

### vs. Scramble (dedoc/scramble)

| Аспект | Scramble | laravel-api |
|--------|---------|-------------|
| **Подход** | Zero-config: выводит спек из кода (типы, FormRequest, маршруты) | Docblock теги: явные `@input`/`@output` аннотации |
| **Интеграция маршрутов** | Использует встроенные маршруты Laravel | Пользовательская маршрутизация через `getMethods()` |
| **Источник документации** | PHP типы, правила FormRequest, return типы | Docblock аннотации |
| **Ручные аннотации** | Опциональные, для edge cases | Обязательные для всех endpoint'ов |
| **Версионирование** | Отсутствует встроенное | Встроенное через наследование классов |
| **CRUD** | Отсутствует | Встроенный CrudService |
| **Усилия на setup** | Минимальные — установить и работает | Умеренные — определить модуль, API класс, provider |
| **Когда использовать** | Стандартные Laravel маршруты, минимальные усилия на документацию | Пользовательская маршрутизация, версионирование, нужен CRUD |

### Резюме: Когда использовать laravel-api

✅ **Выбирайте laravel-api когда:**
- Нужны версионированные API с наследованием между версиями
- Хотите интегрированную маршрутизацию + документацию из единого источника
- Нужен CRUD скаффолдинг с фильтрацией, сортировкой, пагинацией
- Предпочитаете лёгкие docblock теги вместо многословных аннотаций
- Хотите стандартизированный формат ответа по всем endpoint'ам

❌ **Выбирайте альтернативы когда:**
- Нужны RESTful resource-style URLs (`/users/{id}`)
- Требуется полная OpenAPI 3.0 совместимость (callbacks, webhooks, discriminator)
- Следуете API-first (design-first) методологии
- Нужны GraphQL или non-REST API
- Хотите zero-annotation документацию (→ Scramble)

## Справочник API

### Контроллеры

| Класс | Методы |
|-------|---------|
| `ApiController` | `success($payload, $status)`, `error($payload, $status)`, `validationError($messages)`, `created($payload)`, `noContent()`, `notFound($message)` |
| `CrudController` | `meta()`, `search(CrudSearchRequest)`, `create(Request)`, `read(Request, int)`, `update(Request, int)`, `delete(int)` |
| `ApiDocumentationController` | `index()` |

### Сервисы

| Класс | Методы |
|-------|---------|
| `CrudService` | `meta()`, `query()`, `resource()`, `collection()`, `search()`, `create()`, `read()`, `update()`, `delete()`, `restore()`, `forceDelete()` |
| `ApiResponseHelper` | `say($data, $status)`, `sayError($data, $status)` |

### Компоненты

| Класс | Методы |
|-------|---------|
| `BaseApi` | `getMethods()`, `make()`, `getOpenApiTemplates()`, `getOpenApiSecurityDefinitions()`, `beforeCallAction()`, `afterCallAction()`, `getMiddleware()` |
| `BaseModule` | `getApi($version)`, `makeApi()`, `getApiVersionList()`, `getApiPrefix()`, `getApiUriPattern()`, `getAvailableApiMethods()` |
| `Meta` | `string()`, `integer()`, `number()`, `boolean()`, `hidden()`, `select()`, `file()`, `action()`, `crud()`, `getOpenApiInputs()`, `getColumnKeys()` |

### Middleware

| Класс | Назначение |
|-------|---------|
| `ApiMiddleware` | Абстрактная база — перехватывает `ApiException` и обычные исключения |
| `RequestIdMiddleware` | Генерирует/распространяет `X-Request-Id`, добавляет в `Log::shareContext()` |

### Исключения

| Класс | Назначение |
|-------|---------|
| `ApiException` | Исключение с полем `errorKey` для структурированных ответов об ошибках |
| `ApiErrorHandler` | Реестр обработчиков исключений по классу с обходом parent класса |

### Facades

| Facade | Разрешает на |
|--------|------------|
| `ApiRequest` | `BaseApiRequest` — версия, контроллер, action, HTTP метод |
| `ApiModule` | `BaseModule` — разрешение версии, конфигурация маршрутов |
| `ApiErrorHandler` | `ApiErrorHandler` — реестр обработчиков исключений |

## Лицензия

MIT License. Смотрите [LICENSE.md](LICENSE.md) для деталей.
