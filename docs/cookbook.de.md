# Cookbook — Schritt-für-Schritt-Rezepte

## Rezept 1: Eine versionierte API von Grund auf erstellen

### Schritt 1: Die API-Klasse erstellen

```php
// app/Api/V1/Api.php
namespace App\Api\V1;

use App\Api\V1\Controllers\UserController;
use App\Api\V1\Controllers\OrderController;
use App\Http\Middleware\AuthMiddleware;
use Dskripchenko\LaravelApi\Components\BaseApi;

/**
 * Meine API v1
 * Version 1 der Anwendungs-API
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

### Schritt 2: Das Modul erstellen

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

### Schritt 3: Den ServiceProvider erstellen

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

### Schritt 4: Einen Controller erstellen

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
     * Alle Benutzer auflisten
     * Gibt eine paginierte Liste von Benutzern zurück.
     *
     * @input integer ?$page Seitennummer
     * @input integer ?$perPage Einträge pro Seite
     *
     * @output integer $id Benutzer-ID
     * @output string $name Benutzername
     * @output string(email) $email Benutzer-E-Mail
     */
    public function list(Request $request): JsonResponse
    {
        $users = User::paginate($request->input('perPage', 15));
        return $this->success($users->toArray());
    }

    /**
     * Benutzer nach ID abrufen
     *
     * @input integer $id Benutzer-ID
     *
     * @output integer $id Benutzer-ID
     * @output string $name Benutzername
     * @output string(email) $email Benutzer-E-Mail
     * @output string(date-time) $createdAt Registrierungsdatum
     */
    public function show(Request $request): JsonResponse
    {
        $user = User::findOrFail($request->input('id'));
        return $this->success($user->toArray());
    }

    /**
     * Einen Benutzer erstellen
     *
     * @input string $name Benutzername
     * @input string(email) $email E-Mail-Adresse
     * @input string $password Passwort
     *
     * @output integer $id Erstellte Benutzer-ID
     */
    public function create(Request $request): JsonResponse
    {
        $user = User::create($request->only(['name', 'email', 'password']));
        return $this->created(['id' => $user->id]);
    }
}
```

### Schritt 5: Den Provider registrieren

```php
// bootstrap/providers.php (Laravel 11+)
return [
    App\Providers\ApiServiceProvider::class,
];
```

### Ergebnis

- `GET  /api/v1/user/list` — Benutzer auflisten
- `GET  /api/v1/user/show?id=1` — Benutzer abrufen
- `POST /api/v1/user/create` — Benutzer erstellen
- `POST /api/v1/user/update` — Benutzer aktualisieren
- `POST /api/v1/user/delete` — Benutzer löschen
- `POST /api/v1/order/create` — Bestellung erstellen
- `GET  /api/doc` — API-Dokumentation (Scalar)

---

## Rezept 2: Eine neue API-Version hinzufügen

```php
// app/Api/V2/Api.php
namespace App\Api\V2;

use App\Api\V1\Api as V1;
use App\Api\V2\Controllers\UserController;

class Api extends V1  // ← erbt alle v1-Aktionen
{
    public static function getMethods(): array
    {
        return [
            'controllers' => [
                'user' => [
                    'controller' => UserController::class, // neuer Controller
                    'actions' => [
                        'delete' => false,                 // Löschen in v2 deaktivieren
                        'archive',                         // neue Aktion hinzufügen
                    ],
                ],
            ],
        ];
    }
}
```

Im Modul registrieren:
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

## Rezept 3: CRUD mit CrudService

### Schritt 1: Model + Migration

```php
// app/Models/Product.php
class Product extends Model
{
    protected $fillable = ['name', 'description', 'price', 'status'];
}
```

### Schritt 2: Den CrudService erstellen

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
            ->string('name', 'Produktname')
            ->string('description', 'Beschreibung')
            ->number('price', 'Preis')
            ->select('status', 'Status', ['active', 'draft', 'archived'])
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

### Schritt 3: Im ServiceProvider binden

```php
use Dskripchenko\LaravelApi\Controllers\CrudController;
use Dskripchenko\LaravelApi\Interfaces\CrudServiceInterface;

// Einzelne CRUD-Entität — einfache Bindung:
$this->app->bind(CrudServiceInterface::class, ProductCrudService::class);

// Mehrere CRUD-Entitäten — kontextuelle Bindung verwenden:
$this->app->when(ProductController::class)
    ->needs(CrudServiceInterface::class)
    ->give(ProductCrudService::class);

$this->app->when(OrderController::class)
    ->needs(CrudServiceInterface::class)
    ->give(OrderCrudService::class);
```

### Schritt 4: In getMethods registrieren

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

### Ergebnis

- `GET  /api/v1/product/meta` — Felddefinitionen
- `POST /api/v1/product/search` — gefilterte, sortierte, paginierte Liste
- `POST /api/v1/product/create` — Datensatz erstellen
- `GET  /api/v1/product/read?id=1` — Datensatz lesen
- `POST /api/v1/product/update` — Datensatz aktualisieren
- `POST /api/v1/product/delete` — Datensatz löschen

### Format der Suchanfrage

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

## Rezept 4: Benutzerdefinierte Middleware

```php
// app/Http/Middleware/ApiAuthMiddleware.php
namespace App\Http\Middleware;

use Dskripchenko\LaravelApi\Exceptions\ApiException;
use Dskripchenko\LaravelApi\Middlewares\ApiMiddleware;
use Illuminate\Http\Request;
use Closure;

/**
 * @header string $Authorization Bearer-Token
 */
class ApiAuthMiddleware extends ApiMiddleware
{
    public function run(Request $request, Closure $next)
    {
        if (!$request->bearerToken()) {
            throw new ApiException('unauthorized', 'Bearer-Token erforderlich');
        }

        return $next($request);
    }
}
```

Das `@header`-Tag im Middleware-Docblock wird in die OpenAPI-Dokumentation aggregiert.

---

## Rezept 5: OpenAPI mit Sicherheitsdefinitionen und Vorlagen

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

Im Controller:
```php
/**
 * Aktuellen Benutzer abrufen
 *
 * @response 200 {UserResponse}
 * @response 401 {Error}
 * @security BearerAuth
 */
public function me(): JsonResponse { ... }
```

---

## Rezept 6: Tests mit MakesHttpApiRequests schreiben

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

## Rezept 7: Benutzerdefinierte Fehlerbehandlung

```php
// In Ihrem AppServiceProvider oder ApiServiceProvider
use Dskripchenko\LaravelApi\Facades\ApiErrorHandler;
use Dskripchenko\LaravelApi\Services\ApiResponseHelper;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

ApiErrorHandler::addErrorHandler(
    ModelNotFoundException::class,
    function (ModelNotFoundException $e) {
        return ApiResponseHelper::sayError([
            'errorKey' => 'not_found',
            'message' => 'Ressource nicht gefunden',
        ], 404);
    }
);

ApiErrorHandler::addErrorHandler(
    AuthenticationException::class,
    function (AuthenticationException $e) {
        return ApiResponseHelper::sayError([
            'errorKey' => 'unauthenticated',
            'message' => 'Authentifizierung erforderlich',
        ], 401);
    }
);
```

Handler unterstützen Vererbung: Ein Handler für `Exception` fängt auch `RuntimeException` ab, da die Elternklassen über `class_parents()` durchlaufen werden.
