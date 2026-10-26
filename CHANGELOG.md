# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [6.2.0] — 2026-10-26

The API beyond HTTP. An action can be called without an HTTP request of its
own (`Runtime\Dispatcher`) — through the same router, middleware and
validation — and on top of that every version can be served to agents as an
**MCP server** and over **JSON-RPC 2.0** with an OpenRPC description. Both are
off unless enabled. The OpenAPI documents now pass validation.

### Upgrading from 6.1

1. **Run `php artisan api:cache` again** if you use it: the cache format is 2.
   A file written by 6.1 is ignored — requests build the map as if there were
   no cache — until the command runs.
2. **Cached specs.** The OpenAPI specs stored on disk by 6.1 (`openapi_path`)
   still carry the invalid parts until they are rebuilt: `api:cache`, or
   `api:doc-clear` and the next `/api/doc` request.
3. **If you read the `getOpenApiConfig()` array**: the schemas of `@output`
   fields no longer carry a `name` key, and empty `required` lists are gone. The
   documents served as JSON write empty `properties` as `{}`.
4. **MCP and JSON-RPC expose actions to whoever can reach their endpoints** —
   every action of every served version, as the caller of the request. Both are
   off by default; when enabling them, restrict `versions`, put authentication
   in `middleware`, and leave out with `'mcp' => false` / `'jsonrpc' => false`
   what should not be called that way.

### Added

- **Calling an action without HTTP: `Runtime\Dispatcher`.**
  `app(Dispatcher::class)->dispatch(new ApiInvocation($version, $controller,
  $action, $params, $headers))` sends the call through the router the way a
  request to the action's URL would — middleware cascade, validation,
  `auth()`, `ApiRequest` — and returns an `ApiResult` (`success`, `payload`,
  `status`, `errorKey()`, `message()`) read from the envelope an HTTP client
  gets. It runs inside the current request as the same caller (headers and
  cookies are passed on, an invocation's own headers on top) and puts that
  request back afterwards: the container's `request`, the facades, `ApiRequest`,
  the router's current route. GET input travels as a query string, the rest as a
  JSON body. The global HTTP middleware is not run again. The groundwork for
  transports other than HTTP.
- `BaseApiRequest::swapInstance()`.
- **MCP server.** With `laravel-api.mcp.enabled`, every served API version is
  an MCP server at `POST /{prefix}/mcp/{version}` (Streamable HTTP, JSON
  answers, no sessions): `initialize`, `ping`, `tools/list`, `tools/call`. Each
  action is a tool named `controller.action`, its input schema the action's
  input as JSON Schema with the templates under `$defs`, GET actions marked
  `readOnlyHint`. A call goes through `Runtime\Dispatcher` — router,
  middleware, validation, as the caller of the MCP request — and returns the
  action's payload as `structuredContent` and text, `isError` when `success`
  is false. `'mcp' => false` in `getMethods()` leaves an action or a controller
  out; `mcp.versions`, `mcp.middleware`, `mcp.allowed_origins` (the `Origin`
  check against DNS rebinding) and `mcp.name` configure it. `api:cache` caches
  the tool lists (cache format 2: files written by 6.1 are ignored and rebuilt
  by the next `api:cache`); a call finds its action through the route map
  without describing the others.
- **JSON-RPC 2.0.** With `laravel-api.jsonrpc.enabled`, every served version
  answers JSON-RPC 2.0 at `POST /{prefix}/rpc/{version}`: methods named
  `controller.action`, by-name params, through `Runtime\Dispatcher` like MCP.
  The payload is the `result`; a failed action is an `error` — `-32602` for a
  validation failure (status 422), `-32000` otherwise — with the status and the
  payload in `data`. Batches and notifications as the specification says (204
  when nothing is answered). `rpc.discover` answers with an OpenRPC 1.3.2
  document of the version, checked against `@open-rpc/schema-utils-js` for
  every fixture API and the example. `'jsonrpc' => false` in `getMethods()`
  leaves an action or a controller out; `jsonrpc.versions`, `jsonrpc.path`,
  `jsonrpc.middleware` configure it.
- `Transports\ServedVersions`, `Transports\NamedOperations` (the `controller.action`
  index shared by MCP and JSON-RPC), `Operations\Schema\JsonSchemas::encodable()`
  (empty `properties` / `$defs` written as `{}`).

### Changed

- The documentation and the `api:lint` hints use `getSchemaTemplates()` and
  `getSecuritySchemes()`. `getOpenApiTemplates()` and
  `getOpenApiSecurityDefinitions()` keep working; the IDE plugin reads both
  names from 1.1.0.

### Fixed

- **The OpenAPI documents validate.** Checked with
  `@apidevtools/swagger-parser`, 14 of the 17 documents produced for the
  fixtures and the example were invalid OpenAPI 3.0, for three reasons, each
  now gone:
  `"properties": []` — PHP's empty array written as a JSON list where a map is
  due (an `@output` without fields, envelope payloads) —, `"required": []` —
  draft 4, which OpenAPI 3.0 builds on, wants at least one name —, and a `name`
  key the generator left inside the schema of every `@output` field. All 17 now
  validate. `getOpenApiConfig()` no longer carries the `name` keys and the empty
  `required` lists; the empty maps become `{}` where the document is written as
  JSON (`/api/doc/{version}` and the specs `api:cache` builds). The TypeScript
  types and the exports are unchanged.

## [6.1.0] — 2026-10-26

The API is now read through one model of its operations: `getMethods()` in one
place, the docblocks in another, and one descriptor per operation that the
router, the OpenAPI document and the linter all share. The generated specs,
routes, types and exports of the test fixtures and the bundled example are
byte-for-byte what 6.0.2 produced (pinned by golden-master snapshots). On top of
it: `api:cache` for production, a linter that sees what `[method]` returns, and
neutral names for the template and security hooks. No public API was removed.

### Upgrading from 6.0

Nothing has to change. Worth knowing:

1. **`api:lint` may report new errors.** It now calls `@input [method]` /
   `@output [method]` the way `/api/doc` does, and reports what they return that
   the spec silently dropped (`callable.*`), and an action whose description
   throws (`operation.describe-failed`). A CI step running `api:lint` can fail
   on code that passed before — on problems that were already there.
2. **Two edge readings of the markup changed** (see Fixed): `{Template}` on
   `@output` counts only as the whole line, and input lines the linter calls
   malformed are no longer documented as string fields.
3. **`api:cache` is opt-in.** `optimize` does not run it; `optimize:clear`
   removes it. Do not use it for an API whose `getMethods()` depends on the
   request or on data.
4. Test suites that reset `BaseApi::$preparedMethods` by reflection keep
   working; `BaseApi::flushPreparedMethods()` is the way now, along with
   `OperationRegistry::flush()`, `OperationDocParser::flush()`,
   `SchemaRegistry::flush()` and `ApiCache::flush()`.

### Added

- **`OperationRegistry`** (`Dskripchenko\LaravelApi\Operations`). The actions of
  an API class, read once from its prepared methods into `Operation` objects:
  keys, controller class and method, HTTP methods, custom route name, `deprecated`,
  `security`, the options as written, and the middleware cascade.
  `BaseApi::operations(string $version = '')` returns it. The dispatcher, the
  route registration, the OpenAPI generator and the linter all read actions
  through it now, instead of walking getMethods() each in its own way. The
  generated spec, routes, types, exports and lint report are unchanged
  (golden-master snapshots).
- **`OperationDocParser`** (`Dskripchenko\LaravelApi\Operations\Doc`). Reads an
  action's docblock once into an `OperationDoc`: the sanitized summary and
  description, the tag lines (`DocLine` — body, origin, and its syntactic form:
  field, `@Model`, `{Template}`, `[method]`, empty, malformed), `@security`,
  `@deprecated`, `@default`/`@example`. The lines of `@input` and `@header`
  include the middleware's, ahead of the method's own. The OpenAPI generator and
  the linter both read docblocks through it now; the spec, types, exports and
  lint report of the fixtures and the example are unchanged.
- **Operation descriptors** (`Dskripchenko\LaravelApi\Operations\DescriptorBuilder`,
  `Operations\Descriptor`). `DescriptorBuilder::for($apiClass)->build($operation)`
  returns one `OperationDescriptor` per HTTP method of an action: the action,
  its `OperationDoc`, the `OperationContext`, `operationId`, `deprecated`,
  `security`, the input (`InputDeclaration` — the JSON Schema of the whole
  input via `schema()`, and how it was declared: flat fields, nested lines,
  `[method]` schemas, `@Model` refs), the `@header` fields, and either the
  success payload (`OutputDeclaration`, without the envelope) or the `@response`
  list. The OpenAPI document is rendered from these; the spec of the fixtures and
  the example is unchanged.
- **`OpenApiRenderer`** (`Services\OpenApi`). Renders the descriptors of an API
  class as the OpenAPI 3.0 document: `render($version)`, `paths($version)`,
  `operation($descriptor)`. The HTTP binding — paths, query/form/JSON body,
  the response envelope, the error answer — is decided there.
  `OpenApiTrait::getOpenApiConfig()` delegates to it; the trait keeps only the
  flags and hooks (`$useResponseTemplates`, `$responseEnvelope`,
  `getOpenApiTemplates()`, `getOpenApiSecurityDefinitions()`).
- **Schema modules** (`Operations\Schema`): `Shorthand` (the template
  shorthand), `SchemaRegistry` (an API class's templates with `Error`/`Success`,
  and its response envelope), `FieldSchemas` (object schemas from docblock
  lines), `SchemaRef` (`#/components/schemas/{Name}` stays the one spelling of
  a template reference; `SchemaRef::name()` takes the name).
- `Operation::operationId(int $methodIndex)`.
- Lint rule **`action.invalid-method-name`**: an `action` key that is not a
  string. The OpenAPI generator used to fail on it; it now falls back to the
  action key, the same way the dispatcher does.
- **`php artisan api:cache` / `api:clear`.** `api:cache` freezes the API map —
  the prepared methods of every version and the route definitions — into
  `bootstrap/cache/laravel-api.php`, and builds the OpenAPI spec of every
  version where `/api/doc` reads it. A spec that cannot be built, or a map
  holding values a PHP file cannot carry (closures, objects), fails the
  command and nothing is written. On an API of ~2,400 actions the route
  definitions took ~13 ms a request; from the cache, with OPcache, nothing
  measurable. `optimize:clear` runs `api:clear`; `optimize` does not run
  `api:cache`, which freezes `getMethods()` and has to be asked for.
- `BaseApi::flushPreparedMethods()`.
- **Neutral hook names: `BaseApi::getSchemaTemplates()` and
  `BaseApi::getSecuritySchemes()`.** The same hooks as `getOpenApiTemplates()`
  and `getOpenApiSecurityDefinitions()`, under names that are not about
  OpenAPI. Override either; the package reads the neutral one, which answers
  with the old hook unless it is overridden itself. The old names are not
  deprecated: the IDE plugin reads them, and the documentation keeps them until
  it reads the neutral ones too.
- **The linter checks what `[method]` returns.** It describes every action the
  way the spec does — calling `@input [method]` / `@output [method]` — and
  reports what used to be dropped without a word: `callable.not-array`,
  `callable.non-string`, `callable.malformed-line`, `callable.unknown-template`;
  and `operation.describe-failed` for an action whose description throws, which
  failed `/api/doc` and `api:cache` while the linter said nothing. The findings
  come from `OperationDescriptor::$notes` (`DescriptorNote`), which the builder
  fills when it leaves something out.

### Changed

- With `route:cache` on, `ApiServiceProvider::makeApiRoutes()` registers only
  the middleware group and skips the routes: they come from the route cache,
  and building them again on every request was thrown away.
- Controller and action keys are looked up literally. A key with a dot in it
  was read as a dot path by the dispatcher and the middleware cascade.

### Fixed

- An empty tag (`@input` with nothing after it) no longer fails the spec with
  `Call to a member function render() on null`; it is skipped, and the linter
  still reports it as `tag.empty`.
- A middleware group holding an alias (`throttle:api`) no longer fails the spec
  with a `ReflectionException`; the aliases are skipped when the group's
  `@input`/`@header` are collected.
- `{Template}` on `@output` is read only as the whole line. It used to be
  matched anywhere in it: with templates on, `@output string $status The
  status, e.g. {Active}` turned the whole response into a `$ref` to `Active`.
- Lines the linter reports as malformed are no longer documented: `@input
  {User} $user` and `@input @not-a-model $x` used to become string fields
  `user` and `x`. (An `@output @Model $field` whose template does not resolve
  still documents `field` as a string, as before.)
- The linter reads `@input` from middleware groups too, as the generator does:
  a `@default` for an input a grouped middleware declares is no longer reported
  as `default.unknown-variable`.

### Deprecated

- `OpenApiTrait::$docBlockFactory`, `$middlewareInputTagCache` and
  `$cachedRawTemplatesByClass` are unused; they go in 7.0. Use
  `OperationDocParser::flush()` and `SchemaRegistry::flush()` to reset the
  caches.

## [6.0.2] — 2026-10-26

### Fixed

- **Versions no longer leak into each other.** `BaseApi::getPreparedMethods()`
  merged the parents' methods into the shared cache instead of a local
  accumulator, so every API prepared after a hierarchy inherited that
  hierarchy's controllers: with `v2 extends v1` and a standalone `v3`, `v3`
  served — and documented — the endpoints of `v1`. Each version now gets only
  its own class and its own parents. If an application relied on the leak, the
  missing controllers have to be declared in that version or inherited
  explicitly.
- **`'action' => true` re-enables an action.** An action disabled in an earlier
  version (`'a' => false`) and turned back on with `'a' => true` was normalized
  to `['action' => true]`: the route answered 404 and `/api/doc/{version}`
  failed with `ReflectionException: Method ...::1() does not exist`, while the
  linter reported nothing. `true` now means the same as a plain list entry —
  the method is named as the action key.

## [6.0.1] — 2026-10-25

### Changed

- **PHP 8.5 is tested.** CI runs the suite on PHP 8.5 with Laravel 12 and 13,
  next to 8.2–8.4. No code changes.

## [6.0.0] — 2026-10-25

### Changed (breaking)

- **`/api/doc` renders the reference of
  [@dskripchenko/openapi](https://github.com/dskripchenko/openapi) instead of
  Scalar.** Every listed version is one spec on the page, with a version
  switcher, full-text search, try-it (forms, your own headers, an auth panel
  with OAuth2), request samples in 13 languages, a TypeScript types tab with a
  `.d.ts` download, PDF through the browser's print dialog, a light/dark/system
  theme and an English or Russian interface. Each version is handed to the page
  with `DocLink`'s slug, so existing deep links land on the same operations.
- **`documentation_script` and `LARAVEL_API_SCALAR_SCRIPT` are removed** in
  favour of `documentation.script` / `LARAVEL_API_DOC_SCRIPT`. The default is
  the pinned `@dskripchenko/openapi@1.0.0` build on jsdelivr. A value pointing at
  a Scalar bundle no longer works: the page calls
  `DskOpenApi.createApiReference()`, which a Scalar bundle does not define, and
  only the server-rendered list of spec links is shown.
- **The page's markup and script changed.** An application that overrides the
  view (`resources/views/vendor/api_module/api/documentation.blade.php`) must
  rebuild its copy from the package's new one: the view now receives
  `documentationOptions` and mounts the page with
  `DskOpenApi.createApiReference('#app', options)`.

### Added

- **`laravel-api.documentation`** — the page's settings: `script`, `title`,
  `locale`, `theme`, `try_it`, `proxy`, `oauth_redirect_url`,
  `persist_credentials` (env `LARAVEL_API_DOC_SCRIPT`, `LARAVEL_API_DOC_TITLE`,
  `LARAVEL_API_DOC_LOCALE`, `LARAVEL_API_DOC_THEME`, `LARAVEL_API_DOC_TRY_IT`,
  `LARAVEL_API_DOC_PROXY`, `LARAVEL_API_DOC_OAUTH_REDIRECT_URL`,
  `LARAVEL_API_DOC_PERSIST_CREDENTIALS`), plus `snippet_targets` and
  `default_snippet`. `oauth_redirect_url` is the OAuth2 redirect URI the try-it
  sign-in returns to (the page's own URL when empty); the identity provider
  must allow it.
- **`php artisan api:doc-assets`** downloads the configured script into
  `public/` (`vendor/laravel-api/openapi/standalone.js` by default; `--from`
  and `--path` override the source and the target), for networks without a CDN.
  Point `LARAVEL_API_DOC_SCRIPT` at the file afterwards.
- **`DocLink::prefilled($version, $controller, $action, $httpMethod, $values)`**
  builds a link that opens an endpoint with its try-it form filled in — path,
  query, header and cookie values, the body, the media type, the server, and
  parameters the spec does not declare. Credentials go in only when `auth` is
  passed explicitly; the page never sends the request by itself; the payload
  rides in the hash and does not reach the server.
  **`DocLink::prefillPayload($values)`** returns the `?f=` payload alone.
- **A CSRF meta tag for a try-it proxy.** When the documentation routes run
  with a session, the page carries `<meta name="csrf-token">`, and the page
  sends it in `X-CSRF-TOKEN` to the endpoint set in `documentation.proxy`.

### Fixed

- **`laravel-api.doc_middleware` was never applied.** The documentation routes
  took their middleware from `BaseModule::getDocMiddleware()`, which returned an
  empty list and ignored the config key. It now returns
  `config('laravel-api.doc_middleware')`, so `/api/doc` and `/api/doc/{version}`
  run with what the key lists — `['web']` for a session (and the CSRF meta), an
  authentication middleware to close the page.

### Upgrading from 5.x

1. Rename `LARAVEL_API_SCALAR_SCRIPT` to `LARAVEL_API_DOC_SCRIPT` in `.env` — or
   drop it to use the default. If it pointed at a self-hosted Scalar bundle,
   replace that file with the openapi script (step 3).
2. In a published `config/laravel-api.php`, replace the `documentation_script`
   key with the `documentation` block from the package's config.
3. Optional, for networks without a CDN: run `php artisan api:doc-assets` and
   set `LARAVEL_API_DOC_SCRIPT=/vendor/laravel-api/openapi/standalone.js`.
4. If you override the documentation view
   (`resources/views/vendor/api_module/api/documentation.blade.php`), copy the
   package's new `resources/views/api/documentation.blade.php` over it and
   reapply your changes.
5. Check `doc_middleware` in a published config: a non-empty value was silently
   ignored before and now takes effect on `/api/doc` and `/api/doc/{version}`.
   A module that overrides `getDocMiddleware()` keeps its own list.
6. Deep links need nothing: anchors built by `DocLink`, the IDE plugin or saved
   as bookmarks open the same operations.

## [5.11.1] — 2026-10-25

### Fixed

- **Duplicate `operationId` for an action served by several HTTP methods.**
  `'method' => ['get', 'post']` produced two operations with the same id, which
  OpenAPI forbids — validators reported it and code generators merged the two.
  The first method keeps the plain `{controller}_{action}` id, so existing ids
  do not change; the others get the method as a suffix
  (`{controller}_{action}_post`). `OperationContext::operationId()` returns the
  same per-method id.

## [5.11.0] — 2026-10-02

### Added

- **`@input [method]` is told which operation it describes.** One controller
  method may serve many routes — a generic CRUD controller registered under a
  controller key per entity — and the fields depend on the route. The method
  was called with nothing, so it could only answer for all routes at once,
  which in practice meant describing no fields at all. It now receives an
  `OperationContext` (version, Api class, controller key, action key,
  controller class and method, HTTP verb, `input`/`output`, the action's
  options) through the container: declare a parameter typed `OperationContext`,
  or any of `$version`, `$controllerKey`, `$actionKey`, `$httpMethod`. A method
  declaring none of them is called exactly as before.
- **A `[method]` may return a JSON Schema object** instead of docblock lines.
  It goes into the spec as it is — `minimum`, `maxLength`, `pattern`,
  `nullable`, `enum`, nested `items` — merged with the operation's other
  `@input` lines (`required` lists united). For POST it is a JSON request body;
  for GET each top-level property becomes a query parameter with its schema.
- **`@output [method]`**, with the same context and the same two return forms.
- **`$tags[]` describes a list of scalars**: `@input string $abilities[]`
  becomes `{type: array, items: {type: string}}`.
- **Linter: `input.undeclared`** (warning). An action whose method visibly
  validates its input — `validate()`, `Validator::make()`, `validator()`, a
  `FormRequest` parameter — while its docblock declares no `@input` at all.
  Narrowed to validating methods so that actions which take nothing are not
  reported.

### Fixed

- Nested notation keeps `required`: `$widgets[].slug` lands in `items.required`,
  `$address.city` in the object's `required`, and a required root line in the
  body's `required`. They were all dropped.
- A root declared after its children (`$widgets[].slug` before `$widgets`) no
  longer wipes them.
- `@output` with nested fields no longer drops the flat siblings: `@output
  integer $id` next to `@output string $owner.name` used to leave `id` out of
  the schema.
- The linter accepts the `[method]` form on `@output`.

## [5.10.1] — 2026-09-14

### Fixed

- `BaseModule::getApi()` declares `$version` as explicitly nullable (`?string`),
  which removes the PHP 8.4 "implicitly nullable parameter" deprecation notice.

## [5.10.0] — 2026-09-03

### Added

- **`$responseEnvelope` — the spec documents the envelope the runtime sends.**
  `ApiResponseHelper::say()` wraps every body in `{success, payload}`, and the
  generated spec described the payload as if it were the whole response — so a
  client written from the documentation reached for fields one level too high.
  `public static $responseEnvelope = true;` on the Api class wraps every
  response that has a schema — `@output` fields, `@output {Template}`,
  `@response 201 {Template}` and the error codes alike — in
  `{success: boolean, payload: <schema>}`, both required. Templates then
  describe payloads; the default `Error` and `Success` components are wrapped
  the same way, and an own `Error` or `Success` is the payload of that answer.
  An array is a custom envelope in the shorthand, with `'{payload}'` marking
  where the body goes, at any depth.

- **Nested structures in the template shorthand.** A map of fields is a nested
  object, a one-element list is an array of that element, and both unfold at
  any depth — `'client!' => ['email' => 'string(email)']`, `['string']`,
  `[['sku' => 'string!']]`. A key ending in `!` marks the field required
  whatever its definition; `'@Model!'` and `'@Model[]!'` do the same for a
  reference. A hand-written schema still passes through, its `properties` and
  `items` unfolded the same way.

- **Linter: `template.envelope-duplicated`.** With the envelope on, a template
  that declares `success` and `payload` itself was written before the option
  existed and would show up as an envelope inside an envelope.

### Fixed

- **A nested array in a template reached the spec untouched.** Anything below
  the first level of `getOpenApiTemplates()` was copied as it was written, so
  `'payload' => ['uuid' => 'string!']` produced an object with no `type` and no
  `properties` — valid JSON, invalid OpenAPI, and the viewers showed the name
  of the schema and nothing else.

- **Any `@` in a template string became a `$ref`.** The reference syntax was
  applied to every string at every depth, description included: a field
  described as `'string Contact @support'` turned into a reference to a
  schema called `support`. Only the first token is a reference now.

- **The examples declared `public static bool $useResponseTemplates`.** The
  property is untyped in the trait, and PHP rejects a typed redeclaration —
  the snippet from the README was a fatal error on load. The examples now
  match the trait.

## [5.9.2] — 2026-08-20

### Fixed

- **A Bruno export died on a field whose example is not a scalar.** `object` and
  `array` are ordinary documented types, their example values are an object and
  an array, and casting either to a string is a fatal error in PHP — so the
  export of any version containing one produced nothing at all.

  Found by exporting a real application's whole spec rather than a fixture: all
  685 of its operations now export, and every example that is not a scalar is
  written as compact JSON, on the one line a `.bru` value gets.

## [5.9.1] — 2026-08-20

### Fixed

- **The cURL export sent `Authorization` whatever the scheme said.** Right for
  most APIs and silently wrong for the rest: the exported command carried a
  header the application does not read, came back 401, and looked like a bad
  credential rather than a bad export. The scheme names its header; nothing has
  to be assumed. Found by exporting a real endpoint of an API whose credential
  travels in `Token`.

- **A Bruno value that is empty no longer leaves a trailing space** — invisible
  in a diff, and the first thing a linter complains about in a collection that
  is committed.

## [5.9.0] — 2026-08-20

### Added

- **`api:export --endpoint=v1.order.create` — one endpoint instead of a
  collection of two hundred.** Which is what somebody about to try a single call
  actually wants, and the reason the export was mostly unused: producing the
  whole API to look at one method is work nobody does twice.

  The endpoint is spelled the way the package names its routes, so the string
  from a route list works unchanged, and `--method=` picks one when the action
  answers several. No exporter has a special case for it: the spec is narrowed
  to a single path first, and each format then does exactly what it already did
  — a Postman collection of one request, one cURL line, a single `.bru`.

- **`--stdout`**, so an export can be piped instead of leaving a file behind.

- **Bruno, as an export format.** A Bruno collection is a directory of plain
  files — a manifest, an environment, one `.bru` per request — and that is the
  point: it lives in the repository next to the code that produces it, and a
  reviewer can read its diff. A Postman collection is one JSON blob and tells a
  reviewer nothing.

  Declared `apiKey` schemes become a `{{token}}` header. A variable, never a
  value: an exported collection is a thing people commit.

### Changed

- **Exporters are an interface now** — `Exporter`, plus `MultiFileExporter` for
  a format that is a directory rather than a document. Nothing that used the
  four existing formats changes; a custom exporter written against the old
  informal shape still satisfies the interface.

## [5.8.0] — 2026-08-20

### Added

- **`DocLink` — a link to one endpoint on the reference page.** The page has
  always been able to address a single operation; nothing outside it could build
  that address. `DocLink::url('v1', 'order', 'create', 'post')` now returns
  `…/api/doc#v1/tag/order/POST/v1/order/create`, and `DocLink::anchor()` returns
  the hash alone — for a ticket, a README, or an IDE that wants to open the
  documentation of the method under the caret.

### Changed

- **The reference page is handed an explicit slug per version.** Left to itself
  the renderer derives it from `info.title`, which is the first line of the API
  class's docblock: rewording that sentence used to change every deep link into
  the page, silently and with no way to notice. The slug is now the version's
  own name — the thing the URL already carries.

  This changes existing hash links once: `#the-integration-api/tag/…` — the
  title, slugified — becomes `#integration/tag/…`. Bookmarks pointing at the old
  form land on the page rather than on the operation. It is the last time they
  move.

  A dotted version reads as it is written — `v1.1` becomes `v1-1`, not `v11`.

## 5.7.1

### Fixed

- **An exception nobody declared a handler for is now reported before it becomes
  a 500.** It used to vanish: the caller got `{"message": "Internal server
  error"}` and nothing was written anywhere — not to the log, not through the
  framework's exception handler, which never saw it at all.

  Found on a staging environment that answered 500 for three nights running.
  The response was the only evidence there was, and it named nothing; the cause
  turned out to be a domain rule refusing a deletion, a sentence long and
  perfectly clear, thrown from a model and swallowed whole.

  Reporting goes through `report()`, so `dontReport`, channels and every other
  logging decision the application has already made keep applying. An exception
  with a registered handler is left alone: a declared refusal is normal
  operation, and logging it would bury the log in it.

## [5.7.0] — 2026-08-18

### Added
- **`api:lint` — a check for the mistakes this package makes silently.**

  Every defect it looks for has the same shape: the application boots, the tests
  pass, and the damage reaches whoever consumes the API. An action whose
  controller method was renamed answers 404 — the same 404 as a mistyped URL, so
  nothing distinguishes "this endpoint is gone" from "someone asked for
  nonsense". A type outside the known set silently becomes `string`. A
  `@response 200 {UserResponse}` naming a template that was never defined
  becomes a `$ref` pointing at nothing, in a spec that still validates.

  The command reads the route map and the docblock markup with the very same
  parser the OpenAPI generator uses — a linter that agreed with itself but not
  with the generator would be worse than none — and reports 28 rules across the
  map (missing or unreachable methods, unknown verbs, absent controller and
  middleware classes) and the markup (unparseable tags, dangling template and
  security references, unknown types, orphaned dot-notation, duplicate variables
  and status codes, stray `@default`/`@example`).

  `--strict` fails on warnings too, which makes it a CI step; `--json` emits the
  report for tooling; `--api-version` narrows it to one version. `--unrouted`
  adds the check for public controller methods no action points at — off by
  default, because a controller may hold helpers that were never meant to be
  endpoints, and the command says out loud when it was skipped rather than
  letting a clean report imply more coverage than it had.

  Available in code as well: `OpenApiLinter::lint()`, or `lintVersionList()` for
  an explicit map. Documented in `docs/{en,ru,de,zh}/linting.md`.

### Changed
- The docblock grammar moved to `Services\OpenApi\DocPatterns`, where the
  generator and the linter share it. The expressions used to be private to
  `OpenApiTrait`, so a second reader had no way to see them; two copies would
  have drifted, and the drift would have been invisible — the linter passing
  what the generator mangles. Behaviour is unchanged.

## [5.6.2] — 2026-08-17

### Changed
- The code's comments are in English, and the documentation follows the shared
  layout. The changelog also gained the entries for the releases it had skipped
  — the file described the package's history with gaps in it.
  Nothing the package does has changed.

## [5.6.1] — 2026-08-04

### Fixed
- **`ApiErrorHandler` lost handlers registered by other providers.** The
  binding was `bind`, so every resolution from the container produced a new
  object carrying only the constructor defaults. Anything the application or a
  neighbouring package registered through
  `ApiErrorHandler::addErrorHandler(...)` in its `boot()` went to an instance
  that was thrown away.

  The failure was silent: no error was raised, the application simply kept
  answering with the default envelope instead of its own. `ValidationException`
  is the telling case — the package ships a default handler for it
  (`errorKey: validation_error`, an `errors` field) which overrode a foreign
  registration with a different response shape. The consumer's frontend read
  its own field, did not find it and showed the user no field errors at all.

  The binding is now a `singleton` — the handler registry lives as long as the
  application.

## [5.6.0] — 2026-08-04

### Fixed
- **`exclude-middleware` now also removes what came from the shared route
  group.** Previously it was subtracted only from the version's own list
  (`array_diff` in `getMiddlewareByControllerAndActionKey`), so it could not
  drop `web`, panel or any other middleware coming from
  `api-middleware-group` — the name promised more than the mechanism
  delivered.

  The exclusion list now travels to the route and is applied through
  `withoutMiddleware()`, which reaches into the group as well.

  The key is read at three levels: version (new), controller and action.

  Why: stateless API versions (HMAC signature instead of a cookie session)
  were forced to drag sessions, cookies and CSRF along from the shared group.
  For a consumer that cost two extra Redis round-trips per request — a session
  read and written where it has no business being.

## 5.5.1

### Fixed
- An array of refs (`'items' => '@BatchItem[]'`) could not carry a description.
  A description next to a plain `$ref` is ignored by OpenAPI 3.0, but the array
  wrapping it is an ordinary object, so there it survives — and that is exactly
  the field a reader needs explained.

## 5.5.0

### Added
- Shorthand template syntax accepts a description: `'id' => 'integer! Record
  identifier'`. Response schemas were type-only, so anything reading the spec —
  an integrator, an SDK generator, a search index — saw field names with no
  hint of what they mean, and the array form was too verbose to use for every
  field.

## 5.4.0

### Added
- `laravel-api.hidden_versions` — versions kept out of the `/api/doc` index.
  Admin panels and other internal surfaces live in the same module, and
  listing them publishes their whole endpoint map to anyone opening the
  reference page. Hidden specs stay reachable by direct URL.

## 5.3.0

### Added
- `deprecated` flag in `getMethods()` — at version, controller or action
  level. `@deprecated` lives in the method's docblock, but one controller
  often serves several API versions, and only the legacy one should be
  marked; the config flag makes that possible without touching the
  controller.

## 5.2.0

### Changed
- **The controller's fully-qualified class name is no longer prepended to
  every endpoint description.** It leaked the internal namespace layout into
  a public document, and for docblocks without a blank-line-separated
  description the field held nothing else — the class name *was* the whole
  description. Set `laravel-api.expose_controller_class` (or
  `LARAVEL_API_EXPOSE_CONTROLLER_CLASS=true`) to restore the old behaviour.

### Fixed
- Inline docblock tags reached the spec verbatim: `{@see \App\Foo::bar()}`,
  `{@link …}` and `{@inheritDoc}` were rendered as-is in summaries and
  descriptions. They are now unwrapped — `@see` keeps the short symbol name,
  `@link` becomes `label (url)`, `@inheritDoc` is dropped.

## 5.1.4

### Fixed
- **`/api/doc` rendered a blank page whenever a spec title contained an
  apostrophe or a newline.** The spec list was embedded as raw JSON inside
  a single-quoted JavaScript string: an apostrophe (`endpoint'ов`,
  `caller's`) broke the script with a syntax error, and an escaped newline
  broke `JSON.parse` itself. Nothing rendered — not even the fallback
  list, since the same broken script populated it. The payload is now
  emitted through Blade's `json` directive (`JSON_HEX_APOS|JSON_HEX_QUOT`),
  and the fallback list of raw specs is rendered server-side, so it no
  longer depends on JavaScript at all.

## 5.1.3

### Added
- `api:doc-clear` command — clears cached OpenAPI spec files
  (`openapi_path`, default `public/openapi`), all versions or a single one
  via `--api-version=`. Specs rebuild on the next `/api/doc` request.
- The provider hooks the cleanup into `optimize:clear` (Laravel 11+
  `ServiceProvider::optimizes()`): deployments that persist the storage
  directory no longer serve stale API docs after a release.

## 5.1.2

### Added
- OpenAPI doc page: `laravel-api.documentation_script` config (env
  `LARAVEL_API_SCALAR_SCRIPT`) makes the Scalar bundle URL configurable —
  self-host it for environments without external-CDN access. The page now
  carries a fallback block with direct raw-spec links so it's never blank
  when the bundle fails to load.

## 5.1.1

### Changed
- `BaseApi::getNormalizedMethods()` is now `protected` (was `private`) —
  subclasses can build their own prepared-methods cache without the parent
  version merge (e.g. panel-scoped Api versions in laravel-admin).

## [5.1.0] - 2026-06-11

### Added
- **Laravel 13 support.** A fresh `laravel new` app ships Laravel 13; the
  package now installs and the full test suite passes on it (PHP 8.3/8.4,
  Testbench 11, Pest 4). Supported range is now Laravel 11–13.
- Per-version OpenAPI spec endpoint `GET /api/doc/{version}` (e.g. `/api/doc/v1`)
  returning the raw JSON document — handy for CI, client generators, or your own
  Redoc/Stoplight setup.
- CI legs for Laravel 13.

### Fixed
- **`/api/doc` showed an empty viewer on Laravel 11+.** The local disk root
  moved to `storage/app/private`, so generated specs were written there while
  the viewer loaded them from `/storage/openapi/*` — a 404 that `storage:link`
  did not resolve. Specs are now served through the `/api/doc/{version}` route,
  decoupling the public URL from the disk layout, so the documentation UI works
  out of the box on any Laravel install.

## [5.0.0] - 2026-06-11

### Changed
- **BREAKING:** narrowed the declared support range to Laravel `^11 || ^12` and
  PHP `^8.2`, dropping the EOL Laravel 6–10 / PHP < 8.2 entries that were no
  longer tested.
- Bound `phpdocumentor/reflection-docblock` to `^6.0` (previously an unbounded
  `*`).
- Rewrote the Packagist description with a clearer value proposition and
  expanded the keyword list; refreshed README badges (CI status, Packagist
  version and downloads) across all language variants.

### Added
- GitHub Actions test workflow.

## [4.3.0] - 2026-05-18

### Fixed
- Middleware enforcement, an OpenAPI cache leak and the default error-handler configuration.

## [4.2.1] - 2026-03-25

### Fixed
- `api:export` and `api:generate-types` honour the API version option.

## [4.2.0] - 2026-03-20

### Added
- `api:export` command with Postman, HTTP Client, Markdown and cURL output.
- Individual named routes registered for every API action.

## [4.1.0] - 2026-03-20

### Added
- TypeScript interface generation from the OpenAPI specification.

## [4.0.5] - 2026-03-20

### Added
- Optional `@output` fields are represented in the OpenAPI schema.

## [4.0.3] - 2026-03-20

### Changed
- Bumped `league/commonmark` to 2.8.2.

## [4.0.2] - 2026-03-08

### Added
- Shorthand template syntax.
- Documentation in German, Russian and Chinese.

### Changed
- Renamed Swagger to OpenApi across the codebase and the documentation.

## [4.0.1] - 2026-03-08

### Changed
- API documentation is rendered with Scalar instead of Swagger UI.

## [4.0.0] - 2026-03-07

### Changed
- Migrated from Swagger 2.0 to OpenAPI 3.0.
- `CrudService`: tightened security and added convenience methods; behaviour is
  configurable through a published config file.

### Added
- OpenAPI generation covers formats, enums, headers, security, nested parameters
  and model references.
- Full documentation and a test coverage report.

## [3.0.7] - 2026-03-03

### Fixed
- `ApiException` handling.

## [3.0.6] - 2025-10-09

### Fixed
- Disabled the local action cache, which served stale definitions.

## [3.0.5] - 2025-10-09

### Fixed
- The API request method is read through `getMethod()`.

## [3.0.4] - 2025-10-09

### Changed
- The captured request supplies the API method by default.

## [3.0.3] - 2025-10-08

### Fixed
- API request construction in the testing trait.

## [3.0.2] - 2025-10-08

### Fixed
- Testing variables are carried into the request.

## [3.0.1] - 2025-10-08

### Fixed
- API version check.

## [3.0.0] - 2025-10-08

### Added
- `MakesHttpApiRequests` testing trait.

## [2.5.3] - 2025-03-12

### Added
- Laravel 12 support.

## [2.5.2] - 2024-07-09

### Added
- Laravel 11 support.

## [2.5.1] - 2024-05-24

### Fixed
- Method resolution when several definitions matched.

## [2.5] - 2023-10-26

### Added
- Several definitions may declare the available HTTP methods for one action.

## [2.4] - 2023-06-08

### Added
- Laravel 10 support.

## [2.3.1] - 2023-05-31

### Fixed
- Registration of the shared API middleware group.

## [2.3.0] - 2023-04-25

### Added
- A shared API middleware group.

## [2.2.0] - 2022-12-15

### Changed
- Reworked the response type returned by API actions.

## [2.1.4] - 2022-12-06

### Added
- The response helper carries the HTTP status code.

## [2.1.3] - 2022-12-05

### Changed
- `getMethods()` is a stub rather than an abstract method, so a controller need not declare it.

## [2.1.2] - 2022-10-10

### Fixed
- Constraint on `php-array-helper`.

## [2.1.1] - 2022-07-15

### Added
- Laravel 9 support.

## [2.1.0] - 2022-01-12

### Added
- HTTP method support per action.
- Laravel 7 and 8 support.

## [2.0.0] - 2021-04-02

### Changed
- Second generation of the package.

## [1.0.0] - 2021-04-02

### Added
- First release: versioned API controllers, `beforeCallAction()` and
  `afterCallAction()` hooks, a default `ApiController` and default error handling.

[5.1.0]: https://github.com/dskripchenko/laravel-api/compare/5.0.0...5.1.0
[5.0.0]: https://github.com/dskripchenko/laravel-api/compare/4.3.0...5.0.0
