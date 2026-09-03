# OpenAPI Docblock-Tags Referenz

Dieses Dokument beschreibt alle Docblock-Tags, die von `OpenApiTrait` fuer die automatische OpenAPI 3.0-Generierung unterstuetzt werden.

## @input — Anfrageparameter

Definiert Eingabeparameter fuer Anfragen. Bei GET-Anfragen werden die Parameter als Query-String uebergeben. Bei POST/PUT/PATCH werden sie im requestBody uebergeben.

### Syntax

```
@input type $variableName Description
@input type ?$variableName Optional parameter
@input type(format) $variableName Type with format
@input type $variableName Description [value1,value2,value3]  ← enum
@input @ModelName Request body as $ref
@input [methodName] Dynamic inputs from a method
```

### Typen

| Typ | OpenAPI-Typ | Hinweise |
|------|-------------|-------|
| `string` | `string` | Standard-Fallback fuer unbekannte Typen |
| `integer` | `integer` | |
| `number` | `number` | |
| `boolean` | `boolean` | |
| `file` | `string` (format: `binary`) | Loest den Content-Type `multipart/form-data` aus |
| `object` | `object` | Wird mit Punkt-Notation fuer verschachtelte Strukturen verwendet |
| `array` | `array` | Wird mit `[]`-Notation fuer Array-Elemente verwendet |

### Format

Format wird in Klammern nach dem Typ angegeben:

```php
@input string(email) $email         // → type: string, format: email
@input string(date-time) $date      // → type: string, format: date-time
@input string(uuid) $id             // → type: string, format: uuid
@input integer(int64) $bigId        // → type: integer, format: int64
@input integer(int32) $count        // → type: integer, format: int32
```

### Enum

Erlaubte Werte werden in eckigen Klammern am Ende der Beschreibung angegeben:

```php
@input string $status Status [active,blocked,pending]
// → enum: ["active", "blocked", "pending"], description: "Status"
```

### Optionale Parameter

Variablennamen werden mit `?` vorangestellt:

```php
@input string $name Required field       // required: true
@input string ?$name Optional field      // required: false
```

### Punkt-Notation (verschachtelte Objekte)

```php
@input object $address Address
@input string $address.city City name
@input string $address.zip ZIP code
```

Erzeugt ein verschachteltes JSON-Schema:
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

### Array-Punkt-Notation

```php
@input array $tags Tags
@input integer $tags[].id Tag ID
@input string $tags[].name Tag name
```

Erzeugt:
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

### Modell-Referenz

```php
@input @OrderCreateRequest
```

Erzeugt `$ref: '#/components/schemas/OrderCreateRequest'` im requestBody. Das Modell muss in `getOpenApiTemplates()` definiert sein.

### Dynamische Eingaben aus Methode

```php
@input [getOpenApiMetaInputs]
```

Ruft die Methode auf dem Controller auf und fuegt die zurueckgegebenen Eingaben zusammen.

---

## @output — Antwortfelder

Definiert Antwortfelder fuer die Standard-200-Antwort.

### Syntax

```
@output type $variableName Description
@output type ?$variableName Optionales Antwortfeld
@output type(format) $variableName Description
@output @ModelName $field Field as $ref
@output @ModelName[] $field Array of $ref
```

### Beispiele

```php
@output integer $id Record ID
@output string(date-time) $createdAt Creation date
@output @User $author Author object         // → $ref: '#/components/schemas/User'
@output @User[] $users List of users         // → type: array, items.$ref: '#/components/schemas/User'
@output object $address Address
@output string $address.city City            // nested output
```

### Optionale Antwortfelder

Variablennamen werden mit `?` vorangestellt, um ein Antwortfeld als optional zu markieren. Erforderliche Felder werden im `required`-Array im generierten OpenAPI-Schema aufgelistet.

```php
@output integer $id Erforderliches Feld    // im "required"-Array
@output string ?$email Optionales Feld     // nicht im "required"-Array
```

---

## @header — Anfrage-Header

Definiert Header-Parameter fuer die Operation.

### Syntax

```
@header type $HeaderName Description
@header type ?$HeaderName Optional header
```

### Beispiele

```php
@header string $Authorization Bearer token
@header string ?$X-Request-Id Optional trace ID
```

Header koennen auch in Middleware-Docblocks definiert werden — sie werden sowohl aus der Controller-Methode als auch aus allen Middlewares in der Kette aggregiert.

---

## @response — Mehrere HTTP-Antworten

Definiert Antworten mit bestimmten HTTP-Statuscodes. Wenn vorhanden, ueberschreibt dies die Standard-200-Antwort von `@output`.

### Syntax

```
@response CODE {TemplateName}
@response CODE Description text
```

### Beispiele

```php
@response 200 {UserResponse}        // → $ref to component schema
@response 422 {ValidationError}     // → $ref to component schema
@response 404 Not found             // → description only
```

Wenn keine `@response`-Tags vorhanden sind, wird `@output` verwendet, um die 200-Antwort zu erstellen.

---

## @security — Operations-Sicherheit

Wendet ein Sicherheitsschema auf die Operation an.

### Syntax

```
@security SchemeName
```

### Beispiel

```php
@security BearerAuth
```

Das Schema muss in `getOpenApiSecurityDefinitions()` der Api-Klasse definiert sein:

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

## @deprecated — Als veraltet markieren

Markiert die Operation als veraltet in der OpenAPI-Spezifikation.

### Syntax

```
@deprecated Optional explanation
```

---

## @default — Standardwert

Setzt einen Standardwert fuer einen Parameter.

### Syntax

```
@default $variableName value
```

### Beispiel

```php
@input integer ?$page Page number
@default $page 1
```

---

## @example — Beispielwert

Setzt einen Beispielwert fuer einen Parameter.

### Syntax

```
@example $variableName value
```

### Beispiel

```php
@input integer ?$page Page number
@example $page 3
```

---

## Kurzschreibweise fuer Templates

Bei der Definition von Templates in `getOpenApiTemplates()` kann eine Kurzschreibweise als String anstelle von ausfuehrlichen Arrays verwendet werden:

| Syntax | Bedeutung | Aequivalentes Array |
|--------|---------|------------------|
| `'integer'` | Optionaler Integer | `['type' => 'integer']` |
| `'string!'` | Erforderlicher String | `['type' => 'string', 'required' => true]` |
| `'string(email)'` | String mit Format | `['type' => 'string', 'format' => 'email']` |
| `'string(date-time)!'` | Format + erforderlich | `['type' => 'string', 'format' => 'date-time', 'required' => true]` |
| `'@Customer'` | `$ref` auf Schema | `['$ref' => '#/components/schemas/Customer']` |
| `'@OrderItem[]'` | Array von `$ref` | `['type' => 'array', 'items' => ['$ref' => '...']]` |
| `'@Customer!'` | Erforderlicher `$ref` | `['$ref' => '...']`, in `required` aufgefuehrt |
| `'client!' => [...]` | Erforderliches Feld mit beliebiger Definition | das `!` am Schluessel setzt das Feld in `required` |
| `['url' => 'string', ...]` | Verschachteltes Objekt | `['type' => 'object', 'properties' => [...]]` |
| `['string']` | Array des Elements | `['type' => 'array', 'items' => ['type' => 'string']]` |
| `[['id' => 'integer!']]` | Array von Objekten | `['type' => 'array', 'items' => ['type' => 'object', ...]]` |

### Beispiel

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

Beide Formate koennen im selben Template gemischt werden. Das Array-Format (`['type' => '...', 'required' => true]`) wird weiterhin vollstaendig unterstuetzt.

### Verschachtelte Strukturen

Eine Feld-Map ist ein verschachteltes Objekt, eine Liste mit einem Element ist ein Array dieses Elements, und die Kurzschreibweise gilt in jeder Tiefe. Ein Schluessel mit `!` am Ende macht das Feld erforderlich, wie auch immer es definiert ist — so werden ein verschachteltes Objekt oder ein Array erforderlich, die keinen String fuer die Markierung haben.

```php
'ProlongationResult' => [
    'uuid'    => 'string(uuid)!',
    'client!' => [                                         // verschachteltes Objekt, erforderlich
        'email' => 'string(email)',
        'phone' => 'string',
    ],
    'phones'  => ['string'],                               // Array von Strings
    'lines'   => [['sku' => 'string!', 'qty' => 'integer']], // Array von Objekten
    'owner'   => '@Customer!',                             // erforderlicher $ref
],
```

Eine Map, deren Schluessel alle OpenAPI-Schema-Schluesselwoerter sind und deren `type` einen echten Typ nennt (`['type' => 'string', 'required' => true]`), ist ein handgeschriebenes Schema und wird wie bisher durchgereicht — seine `properties` und `items` werden genauso aufgeloest.

---

## Antwort-Envelope

`ApiResponseHelper::say()` verpackt jeden JSON-Body — Erfolg wie Fehler — in `{success, payload}`. Standardmaessig dokumentiert die Spezifikation nur die Payload, und ein Client sieht Felder, die zur Laufzeit eine Ebene tiefer liegen. `$responseEnvelope` auf der Api-Klasse dokumentiert den Envelope:

```php
class Api extends BaseApi {
    public static $useResponseTemplates = true;
    public static $responseEnvelope = true;
}
```

Ist er eingeschaltet:

- wird jede Antwort mit Schema — `@output`-Felder, `@output {Template}`, `@response 201 {Template}`, `@response 422 {Error}` — zu `{success: boolean, payload: <Schema>}`, beide Felder erforderlich;
- beschreibt jedes Template eine Payload: `'ProlongationAvailable' => ['uuid' => 'string!', ...]`, nicht `['success' => ..., 'payload' => [...]]`. Der Linter warnt vor einem Template, das den Envelope selbst traegt (`template.envelope-duplicated`);
- werden die Standard-Komponenten `Error` und `Success` genauso verpackt, und ein eigenes `Error`- oder `Success`-Template ist die Payload dieser Antwort.

Ein eigener Envelope ist eine Feld-Map in der Kurzschreibweise, in der der String `'{payload}'` die Stelle des Body-Schemas markiert:

```php
public static $responseEnvelope = [
    'ok!'   => 'boolean',
    'data!' => '{payload}',
    'meta'  => '@Meta',
];
```

`{payload}` darf in jeder Tiefe stehen (`'result' => ['data!' => '{payload}']`). Der TypeScript-Generator typisiert den Envelope wie jedes Objekt, `payload` bekommt das Interface des Templates.

Beide Eigenschaften sind im Trait untypisiert; die Ueberschreibung lautet daher `public static $responseEnvelope`, ohne Typ: PHP lehnt eine typisierte Neudeklaration einer untypisierten statischen Eigenschaft ab.

---

## Automatische Content-Type-Erkennung

Der Content-Type fuer POST-requestBody wird automatisch bestimmt:

| Bedingung | Content-Type |
|-----------|-------------|
| Enthaelt Eingabe vom Typ `file` | `multipart/form-data` |
| Enthaelt Punkt-Notation (verschachtelte) Eingaben | `application/json` |
| Enthaelt Modell-Referenz (`@ModelName`) | `application/json` |
| Nur flache Eingaben | `application/x-www-form-urlencoded` |

---

## Vollstaendiges Beispiel

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
 * @output string ?$notes Optionale Anmerkungen
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
