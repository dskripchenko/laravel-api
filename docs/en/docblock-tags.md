# OpenAPI Docblock Tags Reference

This document describes all docblock tags supported by `OpenApiTrait` for automatic OpenAPI 3.0 generation.

## @input — Request parameters

Defines request input parameters. For GET requests, parameters go to query string. For POST/PUT/PATCH, they go to requestBody.

### Syntax

```
@input type $variableName Description
@input type ?$variableName Optional parameter
@input type(format) $variableName Type with format
@input type $variableName Description [value1,value2,value3]  ← enum
@input @ModelName Request body as $ref
@input [methodName] Dynamic inputs from a method
```

### Types

| Type | OpenAPI type | Notes |
|------|-------------|-------|
| `string` | `string` | Default fallback for unknown types |
| `integer` | `integer` | |
| `number` | `number` | |
| `boolean` | `boolean` | |
| `file` | `string` (format: `binary`) | Triggers `multipart/form-data` content type |
| `object` | `object` | Used with dot-notation for nested structures |
| `array` | `array` | Used with `[]` notation for array items |

### Format

Specify format in parentheses after type:

```php
@input string(email) $email         // → type: string, format: email
@input string(date-time) $date      // → type: string, format: date-time
@input string(uuid) $id             // → type: string, format: uuid
@input integer(int64) $bigId        // → type: integer, format: int64
@input integer(int32) $count        // → type: integer, format: int32
```

### Enum

Specify allowed values in square brackets at the end of description:

```php
@input string $status Status [active,blocked,pending]
// → enum: ["active", "blocked", "pending"], description: "Status"
```

### Optional parameters

Prefix variable name with `?`:

```php
@input string $name Required field       // required: true
@input string ?$name Optional field      // required: false
```

### Dot-notation (nested objects)

```php
@input object $address Address
@input string $address.city City name
@input string $address.zip ZIP code
```

Generates nested JSON schema:
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

### Array dot-notation

```php
@input array $tags Tags
@input integer $tags[].id Tag ID
@input string $tags[].name Tag name
```

Generates:
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

### Model reference

```php
@input @OrderCreateRequest
```

Generates `$ref: '#/components/schemas/OrderCreateRequest'` in requestBody. The model must be defined in `getOpenApiTemplates()`.

### Dynamic inputs from method

```php
@input [getOpenApiMetaInputs]
```

Calls the method on the controller and merges returned inputs.

---

## @output — Response fields

Defines response body fields for the default 200 response.

### Syntax

```
@output type $variableName Description
@output type ?$variableName Optional response field
@output type(format) $variableName Description
@output @ModelName $field Field as $ref
@output @ModelName[] $field Array of $ref
```

### Examples

```php
@output integer $id Record ID
@output string(date-time) $createdAt Creation date
@output @User $author Author object         // → $ref: '#/components/schemas/User'
@output @User[] $users List of users         // → type: array, items.$ref: '#/components/schemas/User'
@output object $address Address
@output string $address.city City            // nested output
```

### Optional output fields

Prefix variable name with `?` to mark a response field as optional. Required fields are listed in the `required` array in the generated OpenAPI schema.

```php
@output integer $id Required field        // in "required" array
@output string ?$email Optional field     // not in "required" array
```

---

## @header — Request headers

Defines header parameters for the operation.

### Syntax

```
@header type $HeaderName Description
@header type ?$HeaderName Optional header
```

### Examples

```php
@header string $Authorization Bearer token
@header string ?$X-Request-Id Optional trace ID
```

Headers can also be defined in middleware docblocks — they are aggregated from both the controller method and all middleware in the chain.

---

## @response — Multiple HTTP responses

Defines responses with specific HTTP status codes. When present, overrides the default 200 response from `@output`.

### Syntax

```
@response CODE {TemplateName}
@response CODE Description text
```

### Examples

```php
@response 200 {UserResponse}        // → $ref to component schema
@response 422 {ValidationError}     // → $ref to component schema
@response 404 Not found             // → description only
```

If no `@response` tags are present, `@output` is used to build the 200 response.

---

## @security — Operation security

Applies a security scheme to the operation.

### Syntax

```
@security SchemeName
```

### Example

```php
@security BearerAuth
```

The scheme must be defined in `getOpenApiSecurityDefinitions()` on the Api class:

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

## @deprecated — Mark as deprecated

Marks the operation as deprecated in the OpenAPI spec.

### Syntax

```
@deprecated Optional explanation
```

---

## @default — Default value

Sets a default value for a parameter.

### Syntax

```
@default $variableName value
```

### Example

```php
@input integer ?$page Page number
@default $page 1
```

---

## @example — Example value

Sets an example value for a parameter.

### Syntax

```
@example $variableName value
```

### Example

```php
@input integer ?$page Page number
@example $page 3
```

---

## Template shorthand syntax

When defining templates in `getOpenApiTemplates()`, you can use a shorthand string notation instead of verbose arrays:

| Syntax | Meaning | Equivalent array |
|--------|---------|------------------|
| `'integer'` | Optional integer | `['type' => 'integer']` |
| `'string!'` | Required string | `['type' => 'string', 'required' => true]` |
| `'string(email)'` | String with format | `['type' => 'string', 'format' => 'email']` |
| `'string(date-time)!'` | Format + required | `['type' => 'string', 'format' => 'date-time', 'required' => true]` |
| `'@Customer'` | `$ref` to schema | `['$ref' => '#/components/schemas/Customer']` |
| `'@OrderItem[]'` | Array of `$ref` | `['type' => 'array', 'items' => ['$ref' => '...']]` |
| `'@Customer!'` | Required `$ref` | `['$ref' => '...']`, listed in `required` |
| `'client!' => [...]` | Required field of any definition | the `!` on the key puts the field in `required` |
| `['url' => 'string', ...]` | Nested object | `['type' => 'object', 'properties' => [...]]` |
| `['string']` | Array of the element | `['type' => 'array', 'items' => ['type' => 'string']]` |
| `[['id' => 'integer!']]` | Array of objects | `['type' => 'array', 'items' => ['type' => 'object', ...]]` |

### Example

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

Both formats can be mixed in the same template. The array format (`['type' => '...', 'required' => true]`) is still fully supported.

### Nested structures

A map of fields is a nested object, a one-element list is an array of that element, and the shorthand works at any depth. A key ending in `!` makes the field required whatever its definition — the way to require a nested object or an array, which have no string to carry the mark.

```php
'ProlongationResult' => [
    'uuid'    => 'string(uuid)!',
    'client!' => [                                         // nested object, required
        'email' => 'string(email)',
        'phone' => 'string',
    ],
    'phones'  => ['string'],                               // array of strings
    'lines'   => [['sku' => 'string!', 'qty' => 'integer']], // array of objects
    'owner'   => '@Customer!',                             // required $ref
],
```

A map whose every key is an OpenAPI schema keyword and whose `type` names a real type (`['type' => 'string', 'required' => true]`) is a hand-written schema and passes through as before — its `properties` and `items` are unfolded the same way.

---

## Response envelope

`ApiResponseHelper::say()` wraps every JSON body — success and error alike — in `{success, payload}`. By default the spec documents the payload alone, so a client reading it sees fields that at runtime live one level deeper. `$responseEnvelope` on the Api class documents the envelope:

```php
class Api extends BaseApi {
    public static $useResponseTemplates = true;
    public static $responseEnvelope = true;
}
```

With it on:

- every response that has a schema — `@output` fields, `@output {Template}`, `@response 201 {Template}`, `@response 422 {Error}` — becomes `{success: boolean, payload: <schema>}`, both fields required;
- every template describes a payload: `'ProlongationAvailable' => ['uuid' => 'string!', ...]`, not `['success' => ..., 'payload' => [...]]`. The linter warns about a template that carries the envelope itself (`template.envelope-duplicated`);
- the default `Error` and `Success` components are wrapped the same way, and an own `Error` or `Success` template is the payload of that answer.

A custom envelope is a map of fields in the shorthand, with the string `'{payload}'` marking where the body schema goes:

```php
public static $responseEnvelope = [
    'ok!'   => 'boolean',
    'data!' => '{payload}',
    'meta'  => '@Meta',
];
```

`{payload}` may sit at any depth (`'result' => ['data!' => '{payload}']`). The TypeScript generator types the envelope like any object, so `payload` comes out as the template's interface.

Both properties are untyped in the trait, so the override is `public static $responseEnvelope`, without a type: PHP rejects a typed redeclaration of an untyped static property.

---

## Content-type auto-detection

The content type for POST requestBody is determined automatically:

| Condition | Content-Type |
|-----------|-------------|
| Has `file` type input | `multipart/form-data` |
| Has dot-notation (nested) inputs | `application/json` |
| Has model reference (`@ModelName`) | `application/json` |
| Flat inputs only | `application/x-www-form-urlencoded` |

---

## Complete example

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
 * @output string ?$notes Optional notes
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
