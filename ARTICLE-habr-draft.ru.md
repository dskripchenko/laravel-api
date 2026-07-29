<!--
МЕТА ДЛЯ HABR (удалить перед публикацией)

Заголовок: Версионированный API на Laravel с авто-документацией OpenAPI за 10 минут
Хабы: PHP, Laravel, API, Open source
Теги: laravel, php, api, openapi, swagger, версионирование api, документация
КДПВ: скриншот интерфейса /api/doc (Scalar) — он наглядный
Cut: после блока «Коротко» / первого абзаца (маркер <cut/> ниже)
-->

# Версионированный API на Laravel с авто-документацией OpenAPI за 10 минут

> **Коротко.** Поставим пакет [`dskripchenko/laravel-api`](https://github.com/dskripchenko/laravel-api), напишем один контроллер — и получим версионированный API (`/api/v1/...`) **и** полноценную спеку OpenAPI 3.0 на `/api/doc`, сгенерированную из того самого PHPDoc, который вы и так написали бы. А потом выпустим `v2`, не копипастя ни одного контроллера.

<cut/>

## Проблема

В любом растущем Laravel-API гниют две вещи:

1. **Версионирование.** Вышел `v1`, потом `v2` должен поменять три эндпоинта, но сохранить остальные двадцать. Вы либо копипастите папку `V2` (и теперь багфиксы живут в двух местах), либо втыкаете в контроллеры ветки `if ($version === 2)`.
2. **Документация.** Спека OpenAPI расходится с кодом ровно в момент мержа. Библиотеки аннотаций (`#[OA\Get(...)]`, гигантские YAML) заставляют описывать API *дважды* — один раз в коде, второй раз в атрибутах.

Ставка этого пакета: **ваш контроллер уже описывает сам себя**. Имя метода, поля запроса, форма ответа — напишите это один раз обычным PHPDoc, а пакет выведет из него и роуты, *и* документацию. Версионирование становится обычным наследованием в PHP.

Поехали.

## Что построим

Маленький `tasks`-API:

- `POST /api/v1/task/list` — список задач
- `POST /api/v1/task/create` — создать задачу
- интерактивная документация на `GET /api/doc` (сырая спека по версии — `/api/doc/{version}`)
- а затем `v2`, который добавляет эндпоинт, **не трогая v1**

Итого: ~4 небольших файла.

## Шаг 0 — Установка

```bash
composer require dskripchenko/laravel-api
```

Опубликуем конфиг (опционально, но удобно увидеть все ручки):

```bash
php artisan vendor:publish --tag=laravel-api-config
```

```php
// config/laravel-api.php
return [
    'prefix'            => 'api',                            // → /api/...
    'uri_pattern'       => '{version}/{controller}/{action}',
    'available_methods' => ['get', 'post', 'put', 'patch', 'delete'],
    'openapi_path'      => 'public/openapi',
    'doc_middleware'    => [],                               // здесь можно закрыть /api/doc
];
```

## Шаг 1 — Пишем контроллер

Ничего экзотического — он наследует `ApiController` пакета, который даёт хелперы ответов (`success()`, `error()`, `validationError()`, `created()`, `noContent()`, `notFound()`). **PHPDoc и есть документация:**

```php
<?php

namespace App\Api\V1\Controllers;

use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends ApiController
{
    /**
     * List tasks
     * Returns the current user's tasks.
     *
     * @input integer ?$page Page number
     * @input string $status Filter by status [open,done]
     *
     * @output integer $id Task id
     * @output string $title Task title
     * @output string $status Current status
     *
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        return $this->success([
            ['id' => 1, 'title' => 'Собрать чемодан во Вьетнам', 'status' => 'done'],
            ['id' => 2, 'title' => 'Написать эту статью', 'status' => 'open'],
        ]);
    }

    /**
     * Create a task
     *
     * @input string $title Task title
     * @input string ?$status Initial status [open,done]
     *
     * @output integer $id New task id
     * @output string $title Task title
     *
     * @return JsonResponse
     */
    public function create(Request $request): JsonResponse
    {
        return $this->created([
            'id'    => 3,
            'title' => $request->input('title'),
        ]);
    }
}
```

> Текст PHPDoc-аннотаций (`@input`, `@output` и заголовки) оставляйте на английском — он попадает прямо в OpenAPI-спеку, которую читают и Swagger UI, и генераторы клиентов.

Несколько правил PHPDoc, которые стоит знать:

- `?$page` → необязательное поле.
- `string $status ... [open,done]` → список в скобках становится **enum** в спеке.
- `@input integer(int64) $id` / `@input string(email) $email` → тип **с форматом**.
- `@input file $avatar` → загрузка файла; 
- `@input @User $user` → `$ref` на компонентную схему.

Каждый ответ обёрнут в единый конверт:

```json
{ "success": true, "payload": { ... } }
```

Ошибки (брошенный `ApiException` или `$this->error()`) приходят так:

```json
{ "success": false, "payload": { "errorKey": "string", "message": "string" } }
```

## Шаг 2 — Связываем версию, модуль и провайдер

Три маленьких класса. **Это весь слой роутинга** — никаких записей в `routes/api.php`.

```php
<?php
// app/Api/V1/Api.php — что отдаёт v1
namespace App\Api\V1;

use Dskripchenko\LaravelApi\Components\BaseApi;
use App\Api\V1\Controllers\TaskController;

class Api extends BaseApi
{
    public static function getMethods(): array
    {
        return [
            'controllers' => [
                'task' => [                          // → /api/v1/task/{action}
                    'controller' => TaskController::class,
                    'actions'    => ['list', 'create'],
                ],
            ],
        ];
    }
}
```

```php
<?php
// app/Api/ApiModule.php — сопоставляет строку версии с классом Api
namespace App\Api;

use Dskripchenko\LaravelApi\Components\BaseModule;

class ApiModule extends BaseModule
{
    public function getApiVersionList(): array
    {
        return [
            'v1' => \App\Api\V1\Api::class,
        ];
    }
}
```

```php
<?php
// app/Providers/ApiServiceProvider.php — подключает модуль
namespace App\Providers;

use Dskripchenko\LaravelApi\Providers\ApiServiceProvider as BaseApiServiceProvider;
use App\Api\ApiModule;

class ApiServiceProvider extends BaseApiServiceProvider
{
    protected function getApiModule(): ApiModule
    {
        return new ApiModule();
    }
}
```

Регистрируем провайдер (Laravel 11/12/13 — `bootstrap/providers.php`):

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\ApiServiceProvider::class,   // 👈
];
```

Всё. Базовый провайдер сам зарегистрирует роуты и эндпоинт `/api/doc` на старте.

## Шаг 3 — Дёргаем

```bash
curl -X POST http://localhost:8000/api/v1/task/list
```

```json
{
  "success": true,
  "payload": [
    { "id": 1, "title": "Собрать чемодан во Вьетнам", "status": "done" },
    { "id": 2, "title": "Написать эту статью", "status": "open" }
  ]
}
```

> Действия по умолчанию — `POST`. Нужен `GET`? Объявите его на уровне действия:
> `'list' => ['action' => 'list', 'method' => ['get']]`.

## Шаг 4 — Награда: бесплатная OpenAPI-документация

Откройте **`GET /api/doc`** в браузере. Вы получаете не сырой JSON, а готовый **интерактивный справочник API** (рендерится через [Scalar](https://github.com/scalar/scalar)) с уже подключённым переключателем версий `v1`, `v2`, … Пакет прошёлся по контроллерам, прочитал PHPDoc и собрал полноценный документ OpenAPI 3.0 — параметры, enum'ы, схемы ответов, всё. Эта документация **не может разойтись** с кодом, потому что она *и есть* код.

Нужна сырая спека для CI, генератора клиентов или своей сборки Redoc/Stoplight? Каждая версия отдаётся как JSON на **`GET /api/doc/{version}`** (например, `/api/doc/v1`) — без `storage:link`, без шага сборки.

Нужны TypeScript-клиенты? Есть генератор:

```bash
php artisan api:generate-types
```

…и экспортёр в Postman / HTTP Client / Markdown / cURL:

```bash
php artisan api:export --format=postman
```

## Шаг 5 — Версионирование без копипасты

Вот часть, которая обычно болит. `v2` должен добавить эндпоинт `archive` и убрать устаревший — но не трогать `v1`. Вы **наследуете** предыдущую версию, на уровне и контроллера, и `Api`.

Контроллер v2 наследует все действия v1 и добавляет новое:

```php
<?php
// app/Api/V2/Controllers/TaskController.php
namespace App\Api\V2\Controllers;

use App\Api\V1\Controllers\TaskController as V1TaskController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends V1TaskController   // наследует list() и create()
{
    /**
     * Archive a task
     *
     * @input integer $id Task id
     *
     * @output boolean $archived Whether it was archived
     */
    public function archive(Request $request): JsonResponse
    {
        return $this->success(['archived' => true]);
    }
}
```

А `Api` версии v2 наследует все действия v1, подменяя контроллер:

```php
<?php
// app/Api/V2/Api.php
namespace App\Api\V2;

use App\Api\V1\Api as V1Api;
use App\Api\V2\Controllers\TaskController;

class Api extends V1Api          // наследует все действия v1…
{
    public static function getMethods(): array
    {
        return [
            'controllers' => [
                'task' => [
                    'controller' => TaskController::class,   // …подменяем только этот
                    'actions'    => [
                        'list',
                        'create',
                        'archive',          // добавляем новое действие
                        'legacyExport' => false,   // отключаем унаследованное
                    ],
                ],
            ],
        ];
    }
}
```

Регистрируем:

```php
// app/Api/ApiModule.php
public function getApiVersionList(): array
{
    return [
        'v1' => \App\Api\V1\Api::class,
        'v2' => \App\Api\V2\Api::class,
    ];
}
```

Теперь `/api/v2/task/...` работает, документация `v2` появляется автоматически, а **v1 не изменился**. Багфикс в общем действии? Чините один раз в базовом классе. Нужен чистый разрыв? Переопределите контроллер. Нужно убить эндпоинт в новой версии? Поставьте ключу действия значение `false` (как `'legacyExport' => false` выше).

Middleware каскадируется так же — глобальный → контроллерный → на действие, с люками `exclude-middleware` / `exclude-all-middleware` на каждом уровне.

## Почему такой подход

| | Библиотеки аннотаций (`#[OA\...]`) | Этот пакет |
|---|---|---|
| Описание API | в коде **и** в атрибутах | один раз, в PHPDoc |
| Версионирование | папки вручную / ветки `if` | наследование PHP |
| Расхождение доков | возможно (отдельный источник) | невозможно (выводится из кода) |
| Роуты | пишутся руками | выводятся из `getMethods()` |

Это не замена целому фреймворку для каждой команды — если вы любите спеки на атрибутах, здесь их не хватит. Но если вы хоть раз грепали контроллер, гадая, совпадает ли документация с кодом, — этот вопрос отпадает совсем.

## Попробовать

```bash
composer require dskripchenko/laravel-api
```

- ⭐ Репозиторий и полная документация: https://github.com/dskripchenko/laravel-api
- 📦 Packagist: https://packagist.org/packages/dskripchenko/laravel-api

Я его поддерживаю — issue, идеи и репорты «у меня сломалось вот так» приветствуются. А чего бы вам не хватило в пакете, заточенном под версионирование?
