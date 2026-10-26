<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="assets/logo-dark.svg">
    <img src="assets/logo-light.svg" alt="laravel-api" width="360">
  </picture>
</p>

# dskripchenko/laravel-api

[![Tests](https://img.shields.io/github/actions/workflow/status/dskripchenko/laravel-api/tests.yml?branch=master&label=tests&logo=github)](https://github.com/dskripchenko/laravel-api/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/dskripchenko/laravel-api?logo=packagist&logoColor=white)](https://packagist.org/packages/dskripchenko/laravel-api)
[![Total Downloads](https://img.shields.io/packagist/dt/dskripchenko/laravel-api)](https://packagist.org/packages/dskripchenko/laravel-api)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](../../LICENSE.md)
[![Laravel](https://img.shields.io/badge/Laravel-11.x--13.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-purple.svg)](https://www.php.net)

🌐 [English](../../README.md) | [Deutsch](../de/README.md) | [中文](../zh/README.md)

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
- [Поддержка IDE](#поддержка-ide)
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
- `GET /api/doc` — автоматически сгенерированная документация API (справочник [@dskripchenko/openapi](https://github.com/dskripchenko/openapi))

## Возможности

| Возможность | Описание |
|---------|-------------|
| **Версионированная маршрутизация** | `api/{version}/{controller}/{action}` с наследованием между версиями |
| **OpenAPI 3.0** | Автоматическая генерация из `@input`/`@output` docblock'ов — без YAML-файлов |
| **Страница документации** | `/api/doc` — все версии с переключателем, поиск, try-it, примеры кода, типы TypeScript; работает без интернета через `api:doc-assets` |
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
| **Проверка разметки** | `api:lint` — ловит переименованные действия, ссылки на несуществующие шаблоны, неизвестные типы |
| **Поддержка IDE** | [Плагин для PhpStorm](https://github.com/dskripchenko/laravel-api-idea) — подсветка, инспекции, быстрые исправления, список эндпоинтов |
| **Помощники тестирования** | `assertApiSuccess()`, `assertApiError()`, `assertApiValidationError()` |
| **Публикуемая конфигурация** | `config/laravel-api.php` — префикс, шаблон URI, HTTP методы |

## Установка

### Требования

- PHP 8.2+
- Laravel 11.x — 13.x

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

### Модель операций

Маршрутизация, документация и линтер читают API через одну модель:

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

`getMethods()` читается в одном месте (`OperationRegistry`), докблоки — в другом
(`OperationDocParser`); `DescriptorBuilder` собирает из них по одному
`OperationDescriptor` на HTTP-метод — вход и выход в виде JSON Schema, не
привязанной к транспорту, и то, как вход объявлен. Как всё это идёт по HTTP,
решает `OpenApiRenderer`. Модель живёт в `src/Operations/`;
`php artisan api:cache` замораживает карту для продакшена (см. «Продакшен»).

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
├── Operations/     OperationRegistry, DescriptorBuilder; Doc/, Descriptor/, Schema/
├── Providers/      ApiServiceProvider, BaseServiceProvider
├── Requests/       BaseApiRequest, CrudSearchRequest
├── Resources/      BaseJsonResource, BaseJsonResourceCollection
├── Services/       ApiResponseHelper, CrudService, OpenApiTypeScriptGenerator, ApiCache, OpenApi/ (OpenApiRenderer), Linter/, Export/
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
    'restored' => true,                  // вернуть action, отключённый в прошлой версии; метод = имя action'а
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

Список скаляров записывается через `[]` без дочернего поля: `@input string $tags[]`.

### Поля, известные только во время выполнения

Когда поля зависят от маршрута — один обобщённый контроллер зарегистрирован под
своим ключом для каждой сущности, — `@input [method]` обращается к методу
контроллера, и методу сообщается, какая операция описывается:

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

Метод может вернуть строки докблока (`['string $email Email']`) или объект JSON
Schema, который попадает в спецификацию как есть — вместе со всеми ограничениями.
`@output [method]` работает так же. Подробнее: [docblock-tags.md](docblock-tags.md#динамические-входные-данные-из-метода).

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
    public static $useResponseTemplates = true;

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

**Нейтральные имена.** `getSchemaTemplates()` и `getSecuritySchemes()` — те же хуки под именами, не привязанными к OpenAPI: шаблоны описывают payload, чем бы он ни передавался. Переопределяйте любое из имён; пакет читает нейтральное, а оно, если не переопределено, отвечает старым хуком. Примеры здесь остаются на старых именах, пока их не начнёт читать [IDE-плагин](#поддержка-ide).

**Shorthand-синтаксис:** `type` — необязательное, `type!` — обязательное, `type(format)` — с форматом, `@Model` — ссылка, `@Model[]` — массив ссылок. Также поддерживается формат массивов (`['type' => '...', 'required' => true]`).

**Конверт ответа.** Каждый ответ реально уходит как `{success, payload}`. `public static $responseEnvelope = true;` на классе Api заставляет спецификацию говорить то же: шаблоны описывают payload, а каждый ответ — поля `@output`, ссылки `{Template}`, коды ошибок — оборачивается. Шаблоны могут быть вложенными (`'client!' => ['email' => 'string(email)']`, `['string']`, `[['id' => 'integer!']]`), а собственный конверт — это массив полей с `'{payload}'` на месте тела. Подробнее: [docblock-tags.md](docblock-tags.md#конверт-ответа).

### Страница документации

`GET /api/doc` отдаёт справочник
[@dskripchenko/openapi](https://github.com/dskripchenko/openapi): один скрипт, без сборки в вашем
проекте. Каждая показываемая версия API — отдельная спецификация на странице, а
сама страница даёт:

- переключатель версий и полнотекстовый поиск;
- «Try it» на каждой операции: форма для параметров и тела запроса, плюс
  собственные query-параметры, заголовки и cookie;
- панель авторизации для схем безопасности спецификации, включая OAuth2;
- примеры запросов на 13 языках;
- вкладку с типами TypeScript и скачивание типов файлом `.d.ts`;
- PDF одной операции или всей спецификации через диалог печати браузера;
- светлую, тёмную или системную тему и интерфейс на английском или русском.

Как устроена каждая из этих возможностей — в
[документации @dskripchenko/openapi](https://github.com/dskripchenko/openapi).
Версии из `laravel-api.hidden_versions` на странице не показываются; их
спецификации по-прежнему отдаются по `/api/doc/{version}`.

Страница настраивается блоком `documentation` в `config/laravel-api.php`:

| Ключ | Env | По умолчанию | |
|------|-----|--------------|---|
| `script` | `LARAVEL_API_DOC_SCRIPT` | `https://cdn.jsdelivr.net/npm/@dskripchenko/openapi@1.0.0/dist/standalone.js` | Скрипт страницы — один файл, стили внутри. |
| `title` | `LARAVEL_API_DOC_TITLE` | `null` | Заголовок страницы; если пусто — заголовок открытой версии. |
| `locale` | `LARAVEL_API_DOC_LOCALE` | `'auto'` | `'en'`, `'ru'` или `'auto'` (язык браузера). |
| `theme` | `LARAVEL_API_DOC_THEME` | `'system'` | `'light'`, `'dark'` или `'system'` (как в ОС). Читатель может переключить тему, выбор запоминается. |
| `try_it` | `LARAVEL_API_DOC_TRY_IT` | `true` | `false` скрывает панели «Try it». |
| `proxy` | `LARAVEL_API_DOC_PROXY` | `null` | URL, через который идут запросы try-it; см. ниже. `null` — запросы уходят прямо со страницы. |
| `oauth_redirect_url` | `LARAVEL_API_DOC_OAUTH_REDIRECT_URL` | `null` | OAuth2 redirect URI, на который возвращается вход из try-it; `null` — собственный URL страницы. Провайдер идентификации должен его разрешать. |
| `persist_credentials` | `LARAVEL_API_DOC_PERSIST_CREDENTIALS` | `false` | Хранить ключи и токены, которые вводит читатель: `false`, `'session'` или `'local'`. |
| `snippet_targets` | — | `null` | Языки примеров кода, по порядку; `null` — все. |
| `default_snippet` | — | `'curl'` | Язык, показываемый первым. |

**Если скрипт не загрузился.** Сервер рендерит обычный список ссылок на сырую
OpenAPI-спецификацию каждой версии, а скрипт заменяет его, когда запускается.
Поэтому при заблокированном CDN страница показывает ссылки, а не пустой экран.

**Сети без CDN.** `api:doc-assets` один раз скачивает настроенный скрипт и кладёт
его в `public/`:

```bash
php artisan api:doc-assets
# Saved … bytes to public/vendor/laravel-api/openapi/standalone.js
# Point the page at it: LARAVEL_API_DOC_SCRIPT=/vendor/laravel-api/openapi/standalone.js
```

```dotenv
LARAVEL_API_DOC_SCRIPT=/vendor/laravel-api/openapi/standalone.js
```

`--from=<url>` скачивает другой адрес (другую версию, внутреннее зеркало);
`--path=<path>` меняет, куда положить файл, относительно `public/`. Запускайте
команду там, где есть интернет — например, на этапе сборки, — и поставляйте файл
вместе с приложением. Когда `script` указывает на локальный путь, для повторного
скачивания команде нужен `--from`.

**Try-it через прокси.** Запросы try-it уходят из браузера читателя, поэтому API
на другом origin должен разрешить их через CORS. Если это невозможно, укажите в
`proxy` URL, который их пересылает. Пакет такой эндпоинт не поставляет — его
пишете и защищаете вы. Он должен:

- принимать `POST`, в JSON-теле которого лежит запрос (`HttpRequest`: метод,
  абсолютный URL, заголовки, тело);
- сверять хост URL со списком разрешённых и требовать тот же вход, что и
  остальная админка, — иначе это открытый релей;
- выполнять запрос и отвечать `200` с ответом в JSON (`HttpResponse`: статус,
  заголовки, тело).

Точный контракт — в
[документации standalone-страницы](https://github.com/dskripchenko/openapi/blob/main/docs/ru/standalone.md#прокси).
Когда маршруты документации работают с сессией (`'doc_middleware' => ['web']`),
на странице есть `<meta name="csrf-token">`, и страница отправляет его значение в
`X-CSRF-TOKEN`, так что маршрут прокси за CSRF-проверкой Laravel принимает вызов.

**Доступ.** `doc_middleware` применяется к `/api/doc` и `/api/doc/{version}`:
добавьте туда middleware аутентификации, чтобы закрыть страницу и спецификации.

### Ссылка на конкретный метод

Страница документации адресует каждую операцию хешем, и `DocLink` собирает его,
не открывая браузер — для задачи в трекере, README или IDE:

```php
use Dskripchenko\LaravelApi\Services\OpenApi\DocLink;

DocLink::url('v1', 'order', 'create', 'post');
// https://example.test/api/doc#v1/tag/order/POST/v1/order/create

DocLink::anchor('v1', 'order', 'create', 'post');  // только хеш, без '#'
```

Первый сегмент — версия API, и это сделано намеренно: странице передаётся явный
слаг на каждую версию, поэтому ссылка переживает переписывание первой строки
докблока. Действие с несколькими HTTP-методами — это несколько операций на
странице, по якорю на каждую.

Якоря, собранные до 6.0, продолжают работать: страница понимает форму
`{version}/tag/{controller}/{METHOD}{path}`, поэтому закладки, задачи и ссылки
из IDE-плагина ведут на те же операции.

### Предзаполненные ссылки

`DocLink::prefilled()` собирает ссылку, которая открывает метод с уже заполненной
формой try-it — для баг-репорта, чата или отчёта о тестировании.
`DocLink::prefillPayload()` возвращает только значение `?f=`.

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

| Ключ | |
|------|---|
| `path`, `query`, `header`, `cookie` | Значения параметров по имени. |
| `body` | Текст тела, как его вводят; массив кодируется в JSON. |
| `mediaType` | Media type тела. |
| `server` | Индекс сервера из спецификации или URL сервера. |
| `vars` | Переменные сервера по имени. |
| `xQuery`, `xHeader`, `xCookie` | Параметры, которых нет в спецификации, — список пар `[name, value]`. |
| `auth` | Учётные данные по имени схемы безопасности. Не попадают в ссылку, если не переданы явно: ссылка уходит дальше, чем рассчитывает автор. |

Значения `path`, `query`, `header`, `cookie` и `vars` превращаются в строки
(`true` → `'true'`, `2` → `'2'`): страница читает каждое значение как текст.

Страница никогда не отправляет запрос сама: она заполняет форму, показывает
просьбу проверить значения, и читатель нажимает «Send». Данные едут в хеше, после
якоря, поэтому не попадают ни на ваш сервер, ни в его логи. Формат описан в
[документации по ссылкам](https://github.com/dskripchenko/openapi/blob/main/docs/ru/links.md#предзаполненные-ссылки).

> Полный справочник тегов: [docblock-tags.ru.md](docblock-tags.md) | Проверка разметки: [linting.md](linting.md) | Рецепты: [cookbook.ru.md](cookbook.md)

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
php artisan api:generate-types --api-version=v1                       # Конкретная версия
php artisan api:generate-types --output=frontend/src/api/types.ts # Свой путь
```

Для `@output integer $id` и `@output string ?$email` генератор создаёт:

```typescript
export interface UserShowOutput {
  id: number;
  email?: string;
}
```

Генерируются схемы компонентов, входные и выходные типы операций. Подробности в [cookbook.ru.md](cookbook.md#рецепт-8-генерация-typescript-интерфейсов).

## Проверка разметки

Разметка ошибается молча: у действия с переименованным методом — 404, тот же
самый, что и на опечатку в адресе, а `@response` со ссылкой на несуществующий
шаблон даёт `$ref` в никуда, причём спецификация остаётся валидной.

```bash
php artisan api:lint            # отчёт
php artisan api:lint --strict   # для CI: провал и на предупреждениях
php artisan api:lint --unrouted # плюс публичные методы, на которые никто не ведёт
```

Читает карту маршрутов и докблоки тем же парсером, что и генератор OpenAPI, а
также сообщает о действии, которое валидирует вход, но ничего не объявляет
(`input.undeclared`). Полный список правил: [linting.md](linting.md).

## Продакшен

```bash
php artisan api:cache            # заморозить карту API, собрать OpenAPI-спецификации
php artisan api:cache --no-docs  # только карту
php artisan api:clear            # снова строить её на каждом запросе
```

Без кеша каждый запрос заново строит маршруты всех версий — вместе с каскадом
middleware каждого action'а; для API в пару тысяч action'ов это около 13 мс
на запрос. `api:cache` пишет карту в `bootstrap/cache/laravel-api.php` и
собирает спецификацию каждой версии туда, откуда её читает `/api/doc`; если
спецификация какой-то версии не собирается, команда падает и ничего не пишет.
При включённом `route:cache` пакет больше не регистрирует свои маршруты
повторно на каждом запросе.

Как и `route:cache`, команда замораживает то, что `getMethods()` вернул в момент
запуска: запускайте её на каждом деплое и не используйте для API, чья карта
зависит от запроса или данных. Нужен OPcache, как и для кешей самого Laravel:
без него чтение файла дороже построения карты. `optimize:clear` вызывает
`api:clear`; `optimize` не вызывает `api:cache`.

## Экспорт API

Экспорт спецификации API в различных форматах:

```bash
php artisan api:export --format=postman    # Postman Collection v2.1
php artisan api:export --format=http       # .http файлы для PHPStorm/VS Code
php artisan api:export --format=markdown   # Автономная документация
php artisan api:export --format=curl       # Bash-скрипт с curl командами
php artisan api:export --format=bruno      # Коллекция Bruno — каталог .bru-файлов
```

Опции: `--api-version=v1` (конкретная версия), `--output=path` (свой путь). По умолчанию генерирует файлы по версиям (`v1.json`, `v1.http`, `v1.md`, `v1.sh`).

Bruno — это каталог, а не документ: манифест, окружение и по `.bru` на запрос.
Поэтому `--output` указывает на каталог, а `--stdout` для него отклоняется.
Смысл формата в том, что коллекция лежит в репозитории рядом с кодом, который
её порождает, и её диф читается.

### Один метод

Коллекция из двухсот запросов — не то, что нужно тому, кто собирается
попробовать один:

```bash
php artisan api:export --endpoint=v1.order.create --format=bruno --stdout
php artisan api:export --endpoint=v1.order.create --format=curl --stdout
php artisan api:export --endpoint=v1.order.create --format=bruno --output=collection/order-create.bru
```

Метод пишется так же, как пакет именует свои роуты — `version.controller.action`,
— то есть скопированное из лога или списка роутов подходит без переделки.
`--method=get` выбирает один, когда действие отвечает на несколько. Формат
значения не имеет и частных случаев ни у кого нет: спека сначала сужается до
одного пути, а дальше каждый экспортёр делает ровно то же, что делал.

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
    'doc_middleware' => [],                                       // Middleware для /api/doc и /api/doc/{version}, например ['web']
    'hidden_versions' => [],                                      // Версии, скрытые с /api/doc
    'documentation' => [                                          // Страница /api/doc — см. «Страница документации»
        'script' => env('LARAVEL_API_DOC_SCRIPT', 'https://cdn.jsdelivr.net/npm/@dskripchenko/openapi@1.0.0/dist/standalone.js'),
        'title' => env('LARAVEL_API_DOC_TITLE'),
        'locale' => env('LARAVEL_API_DOC_LOCALE', 'auto'),            // 'en', 'ru', 'auto'
        'theme' => env('LARAVEL_API_DOC_THEME', 'system'),            // 'light', 'dark', 'system'
        'try_it' => env('LARAVEL_API_DOC_TRY_IT', true),
        'proxy' => env('LARAVEL_API_DOC_PROXY'),
        'oauth_redirect_url' => env('LARAVEL_API_DOC_OAUTH_REDIRECT_URL'),
        'persist_credentials' => env('LARAVEL_API_DOC_PERSIST_CREDENTIALS', false), // false, 'session', 'local'
        'snippet_targets' => null,                                    // null — все языки
        'default_snippet' => 'curl',
    ],
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

## Поддержка IDE

Разметку, которую читает этот пакет, PhpStorm не знает ни в какой грамматике —
она выглядит серой прозой, и всё, на чём пакет молча ломается, там ломается так
же молча. Для этого есть плагин:
**[dskripchenko/laravel-api-idea](https://github.com/dskripchenko/laravel-api-idea)**.

**Установка.** Раздаётся из собственного репозитория, не из JetBrains
Marketplace. Один раз добавьте репозиторий плагинов — Settings | Plugins | ⚙ |
*Manage Plugin Repositories…* | **+** —

```
https://raw.githubusercontent.com/dskripchenko/laravel-api-idea/main/updatePlugins.xml
```

после чего ставьте *Laravel API* из вкладки Marketplace как обычно; обновления
приходят тем же путём. Вставляйте адрес без пробела в конце — IDE закодирует его
в URL, и получившийся 404 покажет как *Connection failed*. Либо возьмите
`laravel-api-idea-<version>.zip` из любого
[релиза](https://github.com/dskripchenko/laravel-api-idea/releases) и поставьте
через *Install Plugin from Disk…*.

PhpStorm или IntelliJ IDEA Ultimate с плагином PHP. IDEA Community не подойдёт —
плагин целиком стоит на PSI плагина PHP, а его JetBrains для Community не
выпускает.

**Пока печатаете**

- Подсвечивает `@input`, `@output`, `@header`, `@response`, `@security`,
  `@default`, `@example` — типы, форматы, переменные, ссылки на шаблоны и коды
  ответа, чтобы строка, которую генератор перестал понимать, перестала выглядеть
  как те, что он ещё понимает.
- Сообщает о неразбираемой разметке там, где она написана, вместо того чтобы
  она молча исчезла из спеки.
- Помечает неизвестные типы, называя последствие: генератор сделает поле
  `string`.
- Сверяет разметку с `$request->validate([...])`. Поле, которое валидируется и
  не описано, показывается на самом правиле, с быстрым исправлением, пишущим тег
  из него: `email` → `string(email)`, правило без `required` → необязательное
  поле, `in:a,b` → перечисление.
- Отмечает шаблон `@response` или схему `@security`, которых никто не объявлял,
  и предлагает объявить шаблон — заведя `getOpenApiTemplates()`, если метода нет.
- Делает молчаливую 404 красной: действие, указывающее на отсутствующий,
  непубличный или статический метод, — ошибка прямо при наборе, с быстрым
  исправлением, которое пишет метод по образцу соседей.
- Дополняет типы, форматы, имена шаблонов, схемы безопасности и коды ответа.

**Навигация**

- Ctrl+Click с `{TemplateName}`, `@Model`, `@security Scheme` и
  `@input [method]` на объявления.
- Find Usages по шаблону ответа — все докблоки, где он назван. Шаблон, который
  читает только генератор, иначе выглядит для IDE неиспользуемым.
- Стрелки в гаттере в обе стороны между картой роутов и контроллерами. Действие
  без стрелки указывает на метод, которого нет.
- Строка над методом контроллера, открывающая страницу этого метода в
  `/api/doc` — как собирается адрес, см.
  [Ссылка на конкретный метод](#ссылка-на-конкретный-метод).
- Все эндпоинты карты роутов в одном тул-окне с поиском и версией, под которой
  каждый отвечает.

**По запросу**

- Запускает `api:lint` и делает находки кликабельными.
- Выгружает эндпоинт под курсором как запрос — Bruno, cURL, HTTP Client, Postman
  или Markdown — через `api:export --endpoint`.

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
| **Поддержка IDE** | Plugin support для `@OA\*` аннотаций | [Плагин](https://github.com/dskripchenko/laravel-api-idea): подсветка, инспекции, быстрые исправления, навигация, список эндпоинтов, запуск `api:lint` |
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
| `BaseApi` | `getMethods()`, `make()`, `getOpenApiTemplates()` / `getSchemaTemplates()`, `getOpenApiSecurityDefinitions()` / `getSecuritySchemes()`, `operations()`, `beforeCallAction()`, `afterCallAction()`, `getMiddleware()` |
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

MIT License. Смотрите [LICENSE.md](../../LICENSE.md) для деталей.
