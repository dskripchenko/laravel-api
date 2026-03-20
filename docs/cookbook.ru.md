# Cookbook — Пошаговые рецепты

## Рецепт 1: Создание версионированного API с нуля

### Шаг 1: Создайте класс API

```php
// app/Api/V1/Api.php
namespace App\Api\V1;

use App\Api\V1\Controllers\UserController;
use App\Api\V1\Controllers\OrderController;
use App\Http\Middleware\AuthMiddleware;
use Dskripchenko\LaravelApi\Components\BaseApi;

/**
 * Мой API v1
 * Версия 1 API приложения
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

### Шаг 2: Создайте модуль

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

### Шаг 3: Создайте ServiceProvider

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

### Шаг 4: Создайте контроллер

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
     * Список всех пользователей
     * Возвращает пагинированный список пользователей.
     *
     * @input integer ?$page Номер страницы
     * @input integer ?$perPage Количество элементов на странице
     *
     * @output integer $id ID пользователя
     * @output string $name Имя пользователя
     * @output string(email) $email Email пользователя
     */
    public function list(Request $request): JsonResponse
    {
        $users = User::paginate($request->input('perPage', 15));
        return $this->success($users->toArray());
    }

    /**
     * Получить пользователя по ID
     *
     * @input integer $id ID пользователя
     *
     * @output integer $id ID пользователя
     * @output string $name Имя пользователя
     * @output string(email) $email Email пользователя
     * @output string(date-time) $createdAt Дата регистрации
     */
    public function show(Request $request): JsonResponse
    {
        $user = User::findOrFail($request->input('id'));
        return $this->success($user->toArray());
    }

    /**
     * Создать пользователя
     *
     * @input string $name Имя пользователя
     * @input string(email) $email Адрес электронной почты
     * @input string $password Пароль
     *
     * @output integer $id ID созданного пользователя
     */
    public function create(Request $request): JsonResponse
    {
        $user = User::create($request->only(['name', 'email', 'password']));
        return $this->created(['id' => $user->id]);
    }
}
```

### Шаг 5: Зарегистрируйте провайдер

```php
// bootstrap/providers.php (Laravel 11+)
return [
    App\Providers\ApiServiceProvider::class,
];
```

### Результат

- `GET  /api/v1/user/list` — список пользователей
- `GET  /api/v1/user/show?id=1` — получить пользователя
- `POST /api/v1/user/create` — создать пользователя
- `POST /api/v1/user/update` — обновить пользователя
- `POST /api/v1/user/delete` — удалить пользователя
- `POST /api/v1/order/create` — создать заказ
- `GET  /api/doc` — документация API (Scalar)

---

## Рецепт 2: Добавление новой версии API

```php
// app/Api/V2/Api.php
namespace App\Api\V2;

use App\Api\V1\Api as V1;
use App\Api\V2\Controllers\UserController;

class Api extends V1  // ← наследует все действия v1
{
    public static function getMethods(): array
    {
        return [
            'controllers' => [
                'user' => [
                    'controller' => UserController::class, // новый контроллер
                    'actions' => [
                        'delete' => false,                 // отключить delete в v2
                        'archive',                         // добавить новое действие
                    ],
                ],
            ],
        ];
    }
}
```

Зарегистрируйте в модуле:
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

## Рецепт 3: CRUD с CrudService

### Шаг 1: Модель + Миграция

```php
// app/Models/Product.php
class Product extends Model
{
    protected $fillable = ['name', 'description', 'price', 'status'];
}
```

### Шаг 2: Создайте CrudService

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
            ->string('name', 'Название товара')
            ->string('description', 'Описание')
            ->number('price', 'Цена')
            ->select('status', 'Статус', ['active', 'draft', 'archived'])
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

### Шаг 3: Привязка в ServiceProvider

```php
use Dskripchenko\LaravelApi\Controllers\CrudController;
use Dskripchenko\LaravelApi\Interfaces\CrudServiceInterface;

// Одна CRUD-сущность — простая привязка:
$this->app->bind(CrudServiceInterface::class, ProductCrudService::class);

// Несколько CRUD-сущностей — используйте контекстную привязку:
$this->app->when(ProductController::class)
    ->needs(CrudServiceInterface::class)
    ->give(ProductCrudService::class);

$this->app->when(OrderController::class)
    ->needs(CrudServiceInterface::class)
    ->give(OrderCrudService::class);
```

### Шаг 4: Регистрация в getMethods

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

### Результат

- `GET  /api/v1/product/meta` — определения полей
- `POST /api/v1/product/search` — фильтрованный, сортированный, пагинированный список
- `POST /api/v1/product/create` — создать запись
- `GET  /api/v1/product/read?id=1` — прочитать запись
- `POST /api/v1/product/update` — обновить запись
- `POST /api/v1/product/delete` — удалить запись

### Формат поискового запроса

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

## Рецепт 4: Пользовательский middleware

```php
// app/Http/Middleware/ApiAuthMiddleware.php
namespace App\Http\Middleware;

use Dskripchenko\LaravelApi\Exceptions\ApiException;
use Dskripchenko\LaravelApi\Middlewares\ApiMiddleware;
use Illuminate\Http\Request;
use Closure;

/**
 * @header string $Authorization Bearer-токен
 */
class ApiAuthMiddleware extends ApiMiddleware
{
    public function run(Request $request, Closure $next)
    {
        if (!$request->bearerToken()) {
            throw new ApiException('unauthorized', 'Требуется Bearer-токен');
        }

        return $next($request);
    }
}
```

Тег `@header` в docblock middleware агрегируется в OpenAPI-документацию.

---

## Рецепт 5: OpenAPI с безопасностью и шаблонами

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

В контроллере:
```php
/**
 * Получить текущего пользователя
 *
 * @response 200 {UserResponse}
 * @response 401 {Error}
 * @security BearerAuth
 */
public function me(): JsonResponse { ... }
```

---

## Рецепт 6: Написание тестов с MakesHttpApiRequests

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

## Рецепт 7: Пользовательские обработчики ошибок

```php
// В вашем AppServiceProvider или ApiServiceProvider
use Dskripchenko\LaravelApi\Facades\ApiErrorHandler;
use Dskripchenko\LaravelApi\Services\ApiResponseHelper;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

ApiErrorHandler::addErrorHandler(
    ModelNotFoundException::class,
    function (ModelNotFoundException $e) {
        return ApiResponseHelper::sayError([
            'errorKey' => 'not_found',
            'message' => 'Ресурс не найден',
        ], 404);
    }
);

ApiErrorHandler::addErrorHandler(
    AuthenticationException::class,
    function (AuthenticationException $e) {
        return ApiResponseHelper::sayError([
            'errorKey' => 'unauthenticated',
            'message' => 'Требуется аутентификация',
        ], 401);
    }
);
```

Обработчики поддерживают наследование: обработчик для `Exception` перехватит `RuntimeException` через обход `class_parents()`.

---

## Рецепт 8: Генерация TypeScript-интерфейсов

Команда `api:generate-types` генерирует TypeScript-интерфейсы из OpenAPI-спецификации.

### Базовое использование

```bash
php artisan api:generate-types
```

По умолчанию типы записываются в `resources/js/shared/api/types.ts`.

### Опции

```bash
# Генерация для конкретной версии API
php artisan api:generate-types --version=v1

# Указать путь к файлу
php artisan api:generate-types --output=frontend/src/api/types.ts
```

### Что генерируется

Для контроллера:

```php
class UserController extends ApiController
{
    /**
     * Получить пользователя по ID
     *
     * @input integer $id ID пользователя
     *
     * @output integer $id ID пользователя
     * @output string $name Имя
     * @output string ?$email Email
     * @output string ?$phone Телефон
     */
    public function show(Request $request): JsonResponse
    {
        return $this->success(User::findOrFail($request->input('id'))->toArray());
    }
}
```

Генератор создаёт:

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

### Маппинг типов

| OpenAPI | TypeScript |
|---------|-----------|
| `string` | `string` |
| `integer`, `number` | `number` |
| `boolean` | `boolean` |
| `file`, `string(binary)` | `File` |
| `object` (без свойств) | `Record<string, unknown>` |
| `array` + items | `Type[]` |
| `$ref` | Имя интерфейса |
| `enum` | `'a' \| 'b' \| 'c'` |

Схемы компонентов из `getOpenApiTemplates()` генерируются как именованные интерфейсы. Для каждой операции создаются типы `{Controller}{Action}Input` и `{Controller}{Action}Output`.

---

## Рецепт 9: Именованные маршруты

Все API-действия автоматически регистрируются как именованные маршруты Laravel. Шаблон именования: `api.{version}.{controller}.{action}`.

### Автоматические имена

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

### Ручное именование

Используйте ключ `name` в конфигурации action для переопределения автоматического имени:

```php
'list' => [
    'action' => 'list',
    'method' => ['get'],
    'name' => 'users.index',             // → api.v1.users.index
],
```

Префикс версии `api.{version}.` добавляется автоматически.

### Использование в коде

```php
// Генерация URL
$url = route('api.v1.user.list');        // → /api/v1/user/list

// В Blade-шаблонах
<a href="{{ route('api.v1.user.show') }}">Пользователь</a>

// Фасад URL
$url = URL::route('api.v1.user.list');
```

Catch-all маршрут `api-endpoint` сохранён как fallback для запросов, не совпавших с именованным маршрутом.

---

## Рецепт 10: Экспорт API в различных форматах

Команда `api:export` конвертирует OpenAPI-спецификацию в готовые к использованию форматы.

### Postman Collection

```bash
php artisan api:export --format=postman
```

Генерирует Postman Collection v2.1 JSON. Импортируйте в Postman — получите все эндпоинты, сгруппированные по контроллерам, с предзаполненными параметрами и переменными окружения (`{{baseUrl}}`, `{{token}}`).

### HTTP Client файлы

```bash
php artisan api:export --format=http
```

Генерирует `.http` файлы для JetBrains IDE (PHPStorm, IntelliJ) и VS Code REST Client. Включает переменные `{{host}}` и все типы тел запросов.

### Markdown документация

```bash
php artisan api:export --format=markdown
```

Генерирует автономную Markdown-документацию с оглавлением, таблицами параметров, полями тела запроса с маркерами обязательности и кодами ответов.

### cURL скрипты

```bash
php artisan api:export --format=curl
```

Генерирует bash-скрипт с готовыми curl-командами. Включает переменные `BASE_URL` и `TOKEN`, JSON/form/multipart тела и заголовки авторизации.

### Общие опции

```bash
# Экспорт конкретной версии
php artisan api:export --format=postman --version=v1

# Свой путь (все версии в один файл)
php artisan api:export --format=markdown --output=docs/api.md
```
