# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
