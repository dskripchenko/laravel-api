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

🌐 [English](../../README.md) | [Русский](../ru/README.md) | [中文](../zh/README.md)

**Ein Laravel-Paket für versionierte API-Routing, automatische OpenAPI-3.0-Dokumentation und CRUD-Gerüstbau.**

Erstellen Sie versionierte APIs mit automatisch generierter OpenAPI-Dokumentation aus PHP-Docblöcken — keine YAML/JSON-Schemas zu verwalten, keine Annotation-Bibliotheken zu erlernen.

## Inhaltsverzeichnis

- [Schnelleinstieg](#schnelleinstieg)
- [Funktionen](#funktionen)
- [Installation](#installation)
- [Architektur](#architektur)
- [API-Versionierung](#api-versionierung)
- [Routing und Middleware](#routing-und-middleware)
- [OpenAPI-3.0-Dokumentation](#openapi-30-dokumentation)
- [CRUD-Gerüstbau](#crud-gerüstbau)
- [Tests](#tests)
- [Konfiguration](#konfiguration)
- [Fehlerbehandlung](#fehlerbehandlung)
- [IDE-Unterstützung](#ide-unterstützung)
- [Vergleich mit Alternativen](#vergleich-mit-alternativen)
- [API-Referenz](#api-referenz)
- [Lizenz](#lizenz)

## Schnelleinstieg

```bash
composer require dskripchenko/laravel-api
```

```php
// 1. Definieren Sie Ihre API-Klasse
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

// 2. Definieren Sie Ihr Modul
class ApiModule extends \Dskripchenko\LaravelApi\Components\BaseModule
{
    public function getApiVersionList(): array
    {
        return ['v1' => Api::class];
    }
}

// 3. Definieren Sie Ihren ServiceProvider
class ApiServiceProvider extends \Dskripchenko\LaravelApi\Providers\ApiServiceProvider
{
    protected function getApiModule() { return new ApiModule(); }
}

// 4. Schreiben Sie einen Controller mit Docblöcken
class UserController extends \Dskripchenko\LaravelApi\Controllers\ApiController
{
    /**
     * List users
     * @input integer ?$page Page number
     * @output integer $id User ID
     * @output string $name User name
     */
    public function list(Request $request): JsonResponse
    {
        return $this->success(User::paginate()->toArray());
    }
}
```

**Ergebnis:**
- `GET /api/v1/user/list` — API-Endpunkt
- `GET /api/doc` — Automatisch generierte API-Dokumentation (Scalar)

## Funktionen

| Funktion | Beschreibung |
|----------|-------------|
| **Versioniertes Routing** | `api/{version}/{controller}/{action}` mit Vererbung zwischen Versionen |
| **OpenAPI 3.0** | Automatisch generiert aus `@input`/`@output` Docblöcken — keine YAML-Dateien |
| **CRUD-Gerüstbau** | Vollständige Suche/Erstellen/Lesen/Aktualisieren/Löschen mit Filterung, Sortierung, Paginierung |
| **Middleware-Kaskade** | Global → Controller → Action mit feiner Ausschließung |
| **Antwort-Templates** | Wiederverwendbare `$ref`-Schemas in `components/schemas` |
| **Sicherheitsschemas** | `@security`-Tag + `securitySchemes` für Bearer/API-Schlüssel-Auth |
| **Verschachtelte Parameter** | Punkt-Notation: `@input string $address.city` → verschachteltes JSON-Schema |
| **Datei-Uploads** | `@input file $avatar` → automatisch `multipart/form-data` |
| **Mehrere Antworten** | `@response 200 {Success}` / `@response 422 {Error}` |
| **Header-Parameter** | `@header string $Authorization` — aggregiert aus Controller + Middleware |
| **Soft Deletes** | Integriertes `restore()` und `forceDelete()` in CrudService |
| **Request Tracing** | `RequestIdMiddleware` — `X-Request-Id` Propagation + Log Context |
| **Optionale Ausgabefelder** | `@output string ?$email` — markiert Antwortfelder als optional im OpenAPI-Schema |
| **TypeScript-Generierung** | `api:generate-types` — generiert TS-Interfaces aus der OpenAPI-Spezifikation |
| **Benannte Routen** | Jede Action wird als benannte Laravel-Route registriert — `route('api.v1.user.list')` |
| **API-Export** | `api:export` — Postman Collection, HTTP Client (.http), Markdown, cURL |
| **Prüfung der Auszeichnung** | `api:lint` — findet umbenannte Aktionen, ins Leere zeigende Templates, unbekannte Typen |
| **IDE-Unterstützung** | [PhpStorm-Plugin](https://github.com/dskripchenko/laravel-api-idea) — Hervorhebung, Inspektionen, Quick-Fixes, Endpunktliste |
| **Test-Helfer** | `assertApiSuccess()`, `assertApiError()`, `assertApiValidationError()` |
| **Veröffentlichbare Konfiguration** | `config/laravel-api.php` — Präfix, URI-Muster, HTTP-Methoden |

## Installation

### Anforderungen

- PHP 8.2+
- Laravel 11.x — 13.x

### Installieren

```bash
composer require dskripchenko/laravel-api
```

### Konfiguration veröffentlichen

```bash
php artisan vendor:publish --tag=laravel-api-config
```

## Architektur

### Request-Lebenszyklus

```
HTTP Request
  └─ ApiServiceProvider (registriert Route: api/{version}/{controller}/{action})
      └─ BaseApiRequest (analysiert Version, Controller, Action aus URI)
          └─ BaseModule::getApi() (Version String → BaseApi Subklasse)
              └─ BaseApi::make()
                  ├─ getMethods() → Controller + Action auflösen
                  ├─ Middleware-Kaskade (global → controller → action)
                  └─ app()->call(Controller@action)
                      └─ JsonResponse {success: true, payload: {...}}
```

### Antwortformat

Jede Antwort ist in einer Standard-Umhüllung eingebunden:

```json
// Erfolg
{"success": true, "payload": {"id": 1, "name": "John"}}

// Fehler
{"success": false, "payload": {"errorKey": "not_found", "message": "User not found"}}

// Validierungsfehler
{"success": false, "payload": {"errorKey": "validation", "messages": {"email": ["Required"]}}}
```

### Verzeichnisstruktur

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

## API-Versionierung

API-Versionen verwenden **PHP-Klassen-Vererbung** — spätere Versionen erweitern frühere:

```php
// V1: vollständige API
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

// V2: erbt V1, modifiziert selektiv
class ApiV2 extends ApiV1 {
    public static function getMethods(): array {
        return ['controllers' => [
            'user' => [
                'controller' => UserControllerV2::class,  // aktualisierter Controller
                'actions' => [
                    'delete' => false,                     // in v2 entfernt
                    'archive',                             // neu in v2
                ],
            ],
        ]];
    }
}
```

V2 erbt automatisch `list`, `show`, `create`, `update` von V1, während der Controller überschrieben und Actions modifiziert werden.

## Routing und Middleware

### Action-Konfiguration

```php
'actions' => [
    'list',                              // einfach: Methodenname = Action-Schlüssel
    'show' => 'getById',                 // Alias: show → ruft getById() auf
    'disabled' => false,                 // deaktivierte Action (404)
    'create' => [
        'action' => 'store',             // expliziter Methodenname
        'method' => ['post'],            // erlaubte HTTP-Methoden (Standard: ['post'])
        'name' => 'orders.store',        // Routenname: api.{version}.orders.store
        'middleware' => [RateLimit::class],
        'exclude-middleware' => [LogMiddleware::class],
        'exclude-all-middleware' => false,
    ],
]
```

### Middleware-Kaskade

```
Globale Middleware (getMethods root)
  └─ Controller Middleware
      └─ Action Middleware
```

Jede Ebene kann Middleware von übergeordneten Ebenen ausschließen, indem sie `exclude-middleware` (spezifisch) oder `exclude-all-middleware` (alle) verwendet.

## OpenAPI-3.0-Dokumentation

Die Dokumentation wird automatisch aus PHP-Docblöcken generiert. Keine YAML- oder JSON-Dateien zu verwalten.

### Basis-Tags

```php
/**
 * Create an order
 * Ausführliche Beschreibung des Endpunkts.
 *
 * @input string $title Order title
 * @input string ?$notes Optional notes
 * @input integer(int64) $amount Amount in cents
 * @input string $status Status [draft,pending,confirmed]
 * @input file ?$attachment Optional file
 *
 * @output integer $id Created order ID
 * @output string(date-time) $createdAt Timestamp
 * @output string ?$notes Optionale Anmerkungen
 */
```

### Verschachtelte Objekte (Punkt-Notation)

```php
/** @input object $address Address
 *  @input string $address.city City
 *  @input string $address.zip ZIP code
 *  @input array $items Order items
 *  @input integer $items[].productId Product
 *  @input integer $items[].quantity Quantity */
```

### Header, Sicherheit, Antworten

```php
/**
 * @header string $Authorization Bearer token
 * @header string ?$X-Request-Id Trace ID
 * @security BearerAuth
 * @response 200 {OrderResponse}
 * @response 422 {ValidationError}
 * @deprecated Use createV2 instead
 */
```

### Antwort-Templates

Wiederverwendbare Schemas aktivieren über `components/schemas`:

```php
class Api extends BaseApi {
    public static bool $useResponseTemplates = true;

    public static function getOpenApiTemplates(): array {
        return [
            'OrderResponse' => [
                'id'         => 'integer!',            // Pflichtfeld Integer
                'title'      => 'string!',             // Pflichtfeld String
                'total'      => 'number',              // Optionales Feld
                'created_at' => 'string(date-time)',   // Mit Format
                'email'      => 'string(email)!',      // Format + Pflicht
                'customer'   => '@Customer',           // $ref zu anderem Schema
                'items'      => '@OrderItem[]',        // Array von $ref
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

**Shorthand-Syntax:** `type` — optional, `type!` — Pflicht, `type(format)` — mit Format, `@Model` — Referenz, `@Model[]` — Array von Referenzen. Das Array-Format (`['type' => '...', 'required' => true]`) wird ebenfalls unterstützt.

### Link auf einen einzelnen Endpunkt

Die Referenzseite adressiert jede Operation über einen Hash, und `DocLink` baut
ihn, ohne einen Browser zu öffnen — für ein Ticket, eine README oder die IDE:

```php
use Dskripchenko\LaravelApi\Services\OpenApi\DocLink;

DocLink::url('v1', 'order', 'create', 'post');
// https://example.test/api/doc#v1/tag/order/POST/v1/order/create

DocLink::anchor('v1', 'order', 'create', 'post');  // nur der Hash, ohne '#'
```

Das erste Segment ist die API-Version, und das ist Absicht: der Seite wird pro
Version ein expliziter Slug übergeben, damit ein Link es überlebt, wenn die
erste Zeile des Docblocks umformuliert wird. Eine Action mit mehreren
HTTP-Methoden ergibt mehrere Operationen auf der Seite — je einen Anker.

> Vollständige Tag-Referenz: [docblock-tags.de.md](docblock-tags.md) | Prüfung: [linting.md](linting.md) | Rezepte: [cookbook.de.md](cookbook.md)

## CRUD-Gerüstbau

Implementieren Sie `CrudService` für sofortige CRUD-Endpunkte:

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

### Suche mit Filterung, Sortierung, Paginierung

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

**Verfügbare Operatoren:** `=`, `!=`, `>`, `<`, `>=`, `<=`, `in`, `not_in`, `like`, `between`, `is_null`, `is_not_null`

**Sicherheit:** LIKE-Werte werden automatisch entsorgt (`%`, `_`, `\`). Alle Schreibvorgänge sind in `DB::transaction()` eingebunden. Filter-Array ist auf 50 Elemente begrenzt.

### Unterstützung für Soft Deletes

CrudService enthält `restore($id)` und `forceDelete($id)` für Modelle mit `SoftDeletes`.

## Tests

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

## TypeScript-Generierung

TypeScript-Interfaces aus der OpenAPI-Spezifikation generieren:

```bash
php artisan api:generate-types                                    # Alle Versionen → resources/js/shared/api/types.ts
php artisan api:generate-types --api-version=v1                       # Bestimmte Version
php artisan api:generate-types --output=frontend/src/api/types.ts # Benutzerdefinierter Pfad
```

Fuer `@output integer $id` und `@output string ?$email` erzeugt der Generator:

```typescript
export interface UserShowOutput {
  id: number;
  email?: string;
}
```

Komponentenschemata, Eingabe- und Ausgabetypen werden generiert. Details in [cookbook.de.md](cookbook.md#rezept-8-typescript-interfaces-generieren).

## Prüfung der Auszeichnung

Die Auszeichnung scheitert lautlos: eine Aktion mit umbenannter Methode
antwortet mit 404 — demselben 404 wie eine falsche URL —, und ein `@response`
mit einem nicht existierenden Template wird zu einem `$ref` ins Leere, während
die Spezifikation valide bleibt.

```bash
php artisan api:lint            # Bericht
php artisan api:lint --strict   # für CI: auch Warnungen sind ein Fehlschlag
php artisan api:lint --unrouted # zusätzlich: öffentliche Methoden ohne Aktion
```

Liest Routen-Tabelle und Docblocks mit demselben Parser wie der
OpenAPI-Generator. Vollständige Regelliste: [linting.md](linting.md).

## API-Export

API-Spezifikation in verschiedenen Formaten exportieren:

```bash
php artisan api:export --format=postman    # Postman Collection v2.1
php artisan api:export --format=http       # .http-Dateien fuer PHPStorm/VS Code
php artisan api:export --format=markdown   # Eigenstaendige Dokumentation
php artisan api:export --format=curl       # Bash-Skript mit curl-Befehlen
php artisan api:export --format=bruno      # Bruno-Collection — ein Verzeichnis von .bru-Dateien
```

Optionen: `--api-version=v1` (bestimmte Version), `--output=path` (benutzerdefinierter Pfad). Standardmaessig werden Dateien pro Version generiert (`v1.json`, `v1.http`, `v1.md`, `v1.sh`).

Bruno ist ein Verzeichnis und kein Dokument — ein Manifest, eine Umgebung und
eine `.bru` je Request. Deshalb benennt `--output` ein Verzeichnis, und
`--stdout` wird abgelehnt. Der Sinn: die Collection liegt im Repository neben
dem Code, der sie erzeugt, und ihr Diff ist lesbar.

### Ein einzelner Endpunkt

Eine Collection mit zweihundert Requests hilft niemandem, der genau einen
ausprobieren will:

```bash
php artisan api:export --endpoint=v1.order.create --format=bruno --stdout
php artisan api:export --endpoint=v1.order.create --format=curl --stdout
php artisan api:export --endpoint=v1.order.create --format=bruno --output=collection/order-create.bru
```

Der Endpunkt wird so geschrieben, wie das Paket seine Routen benennt —
`version.controller.action` —, also passt das aus einem Log Kopierte
unveraendert. `--method=get` waehlt eine aus, wenn die Action mehrere
beantwortet. Kein Format hat dafuer einen Sonderfall: die Spec wird zuerst auf
einen Pfad verengt, danach macht jeder Exporter genau das, was er ohnehin tat.

## Konfiguration

Veröffentlichen Sie die Konfigurationsdatei:

```bash
php artisan vendor:publish --tag=laravel-api-config
```

```php
// config/laravel-api.php
return [
    'prefix' => 'api',                                          // URL-Präfix
    'uri_pattern' => '{version}/{controller}/{action}',          // Route-Muster
    'available_methods' => ['get', 'post', 'put', 'patch', 'delete'],
    'openapi_path' => 'public/openapi',                           // OpenAPI JSON-Ausgabe
    'doc_middleware' => [],                                       // Middleware für /api/doc
];
```

## Fehlerbehandlung

### ApiException

```php
throw new ApiException('payment_failed', 'Insufficient funds');
// → {"success": false, "payload": {"errorKey": "payment_failed", "message": "Insufficient funds"}}
```

### Benutzerdefinierte Fehlerbehandlung

```php
use Dskripchenko\LaravelApi\Facades\ApiErrorHandler;

ApiErrorHandler::addErrorHandler(
    ModelNotFoundException::class,
    fn($e) => ApiResponseHelper::sayError(['errorKey' => 'not_found', 'message' => 'Not found'], 404)
);
```

Handler unterstützen **Vererbung**: Das Registrieren eines Handlers für `Exception` wird auch `RuntimeException` via `class_parents()` Durchlauf abfangen.

### RequestIdMiddleware

Fügen Sie zu Ihrem Middleware-Stack für Request Tracing hinzu:

```php
// Liest X-Request-Id aus Request-Header oder generiert UUID
// Fügt request_id zu Log::shareContext() hinzu
// Setzt X-Request-Id auf Response-Header
Dskripchenko\LaravelApi\Middlewares\RequestIdMiddleware::class
```

## IDE-Unterstützung

Die Markierungen, die dieses Paket liest, kennt PhpStorm in keiner Grammatik —
sie erscheinen als grauer Fließtext, und alles, woran das Paket still scheitert,
scheitert dort ebenso still. Dafür gibt es ein Plugin:
**[dskripchenko/laravel-api-idea](https://github.com/dskripchenko/laravel-api-idea)**.

**Installation.** Es wird aus dem eigenen Repository verteilt, nicht über den
JetBrains Marketplace. Fügen Sie das Plugin-Repository einmalig hinzu —
Settings | Plugins | ⚙ | *Manage Plugin Repositories…* | **+** —

```
https://raw.githubusercontent.com/dskripchenko/laravel-api-idea/main/updatePlugins.xml
```

danach installieren Sie *Laravel API* wie gewohnt aus dem Marketplace-Tab;
Updates kommen auf demselben Weg. Fügen Sie die Adresse ohne abschließendes
Leerzeichen ein — die IDE kodiert es in die URL, und der daraus folgende 404
wird als *Connection failed* gemeldet. Oder nehmen Sie
`laravel-api-idea-<version>.zip` aus einem beliebigen
[Release](https://github.com/dskripchenko/laravel-api-idea/releases) und
verwenden *Install Plugin from Disk…*.

PhpStorm oder IntelliJ IDEA Ultimate mit dem PHP-Plugin. IDEA Community kann es
nicht ausführen — das Plugin steht vollständig auf dem PSI des PHP-Plugins, und
das veröffentlicht JetBrains für Community nicht.

**Während des Tippens**

- Hebt `@input`, `@output`, `@header`, `@response`, `@security`, `@default`,
  `@example` hervor — Typen, Formate, Variablen, Template-Referenzen und
  Statuscodes, damit eine Zeile, die der Generator nicht mehr versteht, aufhört
  wie die zu aussehen, die er noch versteht.
- Meldet nicht parsebare Markierungen dort, wo sie stehen, statt sie stillschweigend
  aus der Spezifikation verschwinden zu lassen.
- Kennzeichnet unbekannte Typen mit der genannten Folge: der Generator macht das
  Feld zu `string`.
- Vergleicht die Markierungen mit `$request->validate([...])`. Ein validiertes,
  aber undokumentiertes Feld wird an der Regel selbst gemeldet, mit einem
  Quick-Fix, der das Tag daraus schreibt: `email` → `string(email)`, eine Regel
  ohne `required` → optionales Feld, `in:a,b` → Aufzählung.
- Markiert ein `@response`-Template oder ein `@security`-Schema, das niemand
  deklariert hat, und bietet an, das Template zu deklarieren — inklusive
  `getOpenApiTemplates()`, wenn die Klasse keines hat.
- Macht den stillen 404 rot: eine Action, die auf eine fehlende, nicht
  öffentliche oder statische Methode zeigt, ist ein Fehler beim Tippen, mit einem
  Quick-Fix, der die Methode nach dem Vorbild der Nachbarn schreibt.
- Vervollständigt Typen, Formate, Template-Namen, Security-Schemata und
  Statuscodes.

**Navigation**

- Ctrl+Klick von `{TemplateName}`, `@Model`, `@security Scheme` und
  `@input [method]` zu ihren Deklarationen.
- Find Usages auf einem Response-Template — jeder Docblock, der es nennt. Ein
  Template, das nur der Generator liest, wirkt für die IDE sonst ungenutzt.
- Gutter-Pfeile in beide Richtungen zwischen Routen-Map und Controllern. Eine
  Action ohne Pfeil zeigt auf eine Methode, die es nicht gibt.
- Eine Zeile über der Controller-Methode, die die Seite genau dieses Endpunkts in
  `/api/doc` öffnet — wie diese Adresse entsteht, steht unter
  [Link auf einen einzelnen Endpunkt](#link-auf-einen-einzelnen-endpunkt).
- Alle Endpunkte der Routen-Map in einem durchsuchbaren Toolfenster, mit der
  Version, unter der jeder antwortet.

**Auf Abruf**

- Führt `api:lint` aus und macht die Fundstellen anklickbar.
- Exportiert den Endpunkt unter dem Cursor als Request — Bruno, cURL, HTTP
  Client, Postman oder Markdown — über `api:export --endpoint`.

## Vergleich mit Alternativen

### vs. Klassisches Laravel-Vorgehen (manuelle Routes + FormRequest)

| Aspekt | Klassisches Laravel | laravel-api |
|--------|------------------|-------------|
| **Route-Definition** | `routes/api.php` — eine Route pro Endpunkt, manuelle Versionierung | `getMethods()` — deklaratives Array, Versionen über Klassen-Vererbung |
| **Versionierung** | Manuell: Route-Gruppen, separate Controller, Copy-Paste | Automatisch: `V2 extends V1`, erben/überschreiben/deaktivieren Actions |
| **Dokumentation** | Separater Prozess: OpenAPI YAML manuell schreiben oder Annotations verwenden | Automatisch generiert aus `@input`/`@output` Docblöcken |
| **Antwortformat** | Ad-hoc pro Controller, keine Standard-Umhüllung | Standardisierte `{success, payload}` Umhüllung überall |
| **CRUD-Boilerplate** | Schreiben Sie Controller + FormRequest + Resource für jede Entität | Implementieren Sie `CrudService` (4 Methoden), erhalten Sie 6+ Endpunkte |
| **Middleware pro Action** | Route-Level Middleware oder Controller Middleware-Gruppen | Feinkörnig: global → controller → action mit Ausschließung |
| **Tests** | `$this->getJson('/api/v1/users')` | `$this->api('v1', 'user', 'list')` + Assertions-Helfer |
| **Lernkurve** | Standard-Laravel-Wissen | Lernen Sie `getMethods()` Struktur + Docblock-Tags |
| **Flexibilität** | Vollständige Kontrolle über alles | Beschränkt auf Paket-Konventionen |
| **Wann verwenden** | Komplexe APIs mit nicht-standardisiertem Routing, GraphQL, Event-getriebene APIs | REST APIs mit Versionierung, Standard-CRUD, automatische Dokumentation benötigt |

**Vorteile von laravel-api:**
- Wartungsfreie Dokumentation — Docblöcke sind die einzige Informationsquelle
- Version-Vererbung eliminiert Code-Duplizierung zwischen API-Versionen
- Standardisiertes Antwortformat über alle Endpunkte hinweg
- CRUD-Gerüstbau reduziert Boilerplate um 60-80%

**Nachteile von laravel-api:**
- Festes URI-Muster (`api/{version}/{controller}/{action}`) — nicht RESTful Ressourcen-Routes
- Opinioniertes Antwortformat — kann nicht leicht zu JSON:API oder HAL wechseln
- Keine native Unterstützung für Ressourcen-URL-Stil (`/users/{id}` vs `/user/show?id=1`)

### vs. L5-Swagger (DarkaOnLine/L5-Swagger)

| Aspekt | L5-Swagger | laravel-api |
|--------|-----------|-------------|
| **Vorgehen** | OpenAPI-first: Annotations schreiben, Docs generieren | Code-first: Docblöcke schreiben, Docs + Routing zusammen |
| **Annotation-Stil** | Vollständige OpenAPI-Annotations (`@OA\Get`, `@OA\Schema`, ...) | Leichte Custom-Tags (`@input`, `@output`, `@header`) |
| **Annotation-Verbosität** | Hoch: 15-30 Zeilen pro Endpunkt für vollständige Spec | Niedrig: 3-10 Zeilen pro Endpunkt |
| **Routing** | Keine — nur Dokumentation, Routes separat definiert | Integriert — Routing + Docs aus einzelnem `getMethods()` |
| **Versionierung** | Manuell — separate Annotation-Gruppen | Integriert — Klassen-Vererbung |
| **CRUD-Generierung** | Keine | Integriert `CrudService` + `CrudController` |
| **Antwortformat** | Beliebig — Sie definieren Schemas | Fest `{success, payload}` Umhüllung |
| **OpenAPI-Abdeckung** | Vollständige OpenAPI 3.0 Spec-Unterstützung | Teilmenge: deckt 90% gängiger Anwendungsfälle ab |
| **IDE-Unterstützung** | Plugin-Unterstützung für `@OA\*` Annotations | [Plugin](https://github.com/dskripchenko/laravel-api-idea): Hervorhebung, Inspektionen, Quick-Fixes, Navigation, Endpunktliste, `api:lint`-Runner |
| **Ökosystem** | Große Community, swagger-php darunter | Kleiner, fokussiertes Paket |
| **Spec-Anpassung** | Vollständige Kontrolle über jedes OpenAPI-Feld | Beschränkt auf unterstützte Tags |
| **Wann verwenden** | API-first Design, vollständige OpenAPI-Konformität benötigt, existierende Routes | Schnelle Entwicklung, versionierte APIs, integriertes Routing + Docs |

**Vorteile gegenüber L5-Swagger:**
- 3-5x weniger Annotations-Code pro Endpunkt
- Routing und Dokumentation sind immer synchron (einzige Quelle)
- Integrierte API-Versionierung mit Vererbung
- CRUD-Gerüstbau enthalten
- Keine Notwendigkeit, die vollständige OpenAPI-Annotations-Spezifikation zu erlernen

**Nachteile gegenüber L5-Swagger:**
- Weniger OpenAPI-Abdeckung (keine Callbacks, Webhooks, Links, Discriminator)
- Festes Antwortformat
- Kleinere Community und Ökosystem
- Nicht geeignet für API-first (Design-first) Workflow

### vs. Scramble (dedoc/scramble)

| Aspekt | Scramble | laravel-api |
|--------|---------|-------------|
| **Vorgehen** | Null-Konfiguration: leitet Spec aus Code ab (Typen, FormRequest, Routes) | Docblock-Tags: explizite `@input`/`@output` Annotations |
| **Route-Integration** | Verwendet Laravels native Routes | Custom Routing über `getMethods()` |
| **Dokumentations-Quelle** | PHP-Typen, FormRequest-Regeln, Return-Typen | Docblock-Annotations |
| **Manuelle Annotations** | Optional, für Grenzfälle | Erforderlich für alle Endpunkte |
| **Versionierung** | Keine integriert | Integriert über Klassen-Vererbung |
| **CRUD** | Keine | Integriert CrudService |
| **Einrichtungsaufwand** | Minimal — installieren und es funktioniert | Moderat — Modul, API-Klasse, Provider definieren |
| **Wann verwenden** | Standard-Laravel-Routes, minimaler Dokumentations-Aufwand | Custom Routing, Versionierung, CRUD-Anforderungen |

### Zusammenfassung: Wann sollte ich laravel-api verwenden?

✅ **Wählen Sie laravel-api wenn:**
- Sie versionierte APIs mit Vererbung zwischen Versionen benötigen
- Sie integriertes Routing + Dokumentation aus einer einzigen Quelle wünschen
- Sie CRUD-Gerüstbau mit Filterung, Sortierung, Paginierung benötigen
- Sie leichte Docblock-Tags gegenüber ausführlichen Annotations bevorzugen
- Sie ein standardisiertes Antwortformat über alle Endpunkte hinweg wünschen

❌ **Wählen Sie Alternativen wenn:**
- Sie RESTful Ressourcen-URL-Stil benötigen (`/users/{id}`)
- Sie vollständige OpenAPI 3.0-Konformität benötigen (Callbacks, Webhooks, Discriminator)
- Sie API-first (Design-first) Methode befolgen
- Sie GraphQL oder Nicht-REST APIs benötigen
- Sie Null-Annotations-Dokumentation wünschen (→ Scramble)

## API-Referenz

### Controller

| Klasse | Methoden |
|-------|---------|
| `ApiController` | `success($payload, $status)`, `error($payload, $status)`, `validationError($messages)`, `created($payload)`, `noContent()`, `notFound($message)` |
| `CrudController` | `meta()`, `search(CrudSearchRequest)`, `create(Request)`, `read(Request, int)`, `update(Request, int)`, `delete(int)` |
| `ApiDocumentationController` | `index()` |

### Services

| Klasse | Methoden |
|-------|---------|
| `CrudService` | `meta()`, `query()`, `resource()`, `collection()`, `search()`, `create()`, `read()`, `update()`, `delete()`, `restore()`, `forceDelete()` |
| `ApiResponseHelper` | `say($data, $status)`, `sayError($data, $status)` |

### Komponenten

| Klasse | Methoden |
|-------|---------|
| `BaseApi` | `getMethods()`, `make()`, `getOpenApiTemplates()`, `getOpenApiSecurityDefinitions()`, `beforeCallAction()`, `afterCallAction()`, `getMiddleware()` |
| `BaseModule` | `getApi($version)`, `makeApi()`, `getApiVersionList()`, `getApiPrefix()`, `getApiUriPattern()`, `getAvailableApiMethods()` |
| `Meta` | `string()`, `integer()`, `number()`, `boolean()`, `hidden()`, `select()`, `file()`, `action()`, `crud()`, `getOpenApiInputs()`, `getColumnKeys()` |

### Middleware

| Klasse | Zweck |
|-------|---------|
| `ApiMiddleware` | Abstrakte Basis — fängt `ApiException` und allgemeine Exceptions ab |
| `RequestIdMiddleware` | Generiert/propagiert `X-Request-Id`, fügt zu `Log::shareContext()` hinzu |

### Exceptions

| Klasse | Zweck |
|--------|---------|
| `ApiException` | Exception mit `errorKey` String für strukturierte Fehlerantworten |
| `ApiErrorHandler` | Registry von Exception-Handlern nach Klasse, mit Parent-Klassen-Durchlauf |

### Facades

| Facade | Auflösung zu |
|--------|------------|
| `ApiRequest` | `BaseApiRequest` — Version, Controller, Action, HTTP-Methode |
| `ApiModule` | `BaseModule` — Version-Auflösung, Route-Konfiguration |
| `ApiErrorHandler` | `ApiErrorHandler` — Exception-Handler-Registry |

## Lizenz

MIT-Lizenz. Siehe [LICENSE.md](../../LICENSE.md) für Details.
