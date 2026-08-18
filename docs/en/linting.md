---
title: Linting
locale: en
status: stable
---

# Linting the API

```bash
php artisan api:lint
```

## Why it exists

This package fails quietly by design, and that is the whole reason for the
command.

An action whose controller method was renamed answers **404** — the same 404 as
a mistyped URL, so nothing in the logs distinguishes "this endpoint is gone" from
"someone asked for nonsense". A type nobody recognises silently becomes
`string`. A `@response 200 {UserResponse}` naming a template that was never
defined becomes a `$ref` pointing at nothing, in a spec that still validates.

In every one of those cases the application boots, the tests pass, and the
mistake reaches whoever consumes the API.

`api:lint` reads the route map and the docblock markup with the very same
parser the OpenAPI generator uses, and reports what would otherwise stay silent.

## Options

| Option | What it does |
|---|---|
| `--api-version=v1` | Lint one version instead of all of them |
| `--strict` | Fail on warnings too, not only on errors |
| `--unrouted` | Also report public controller methods no action points at |
| `--json` | Emit the report as JSON |

Exit code is `1` when there are errors — or, with `--strict`, when there are
warnings. That makes it a CI step:

```yaml
- run: php artisan api:lint --strict
```

`--unrouted` is off by default because a controller may hold helper methods
that were never meant to be endpoints, and a linter that complains about them
is a linter people stop reading. The command says so in its own output rather
than quietly covering less than the reader assumes.

## What it checks

### The route map

| Rule | Severity | What it means |
|---|---|---|
| `action.missing-method` | error | The action points at a controller method that does not exist. **This is the 404 nobody notices.** |
| `action.unreachable-method` | error | The method exists but is not public, or is static — it can never serve a request |
| `action.unknown-http-method` | error | A verb outside `laravel-api.available_methods` |
| `controller.missing-class` | error | The `controller` key names a class that does not exist |
| `controller.missing-key` | error | The controller entry has no `controller` key at all |
| `middleware.missing-class` | error | A middleware class in the cascade does not exist |
| `api.missing-class` | error | `getApiVersionList()` maps a version to a missing class |
| `controller.unrouted-method` | warning | A public method no action points at (`--unrouted` only) |

Bare middleware names without a namespace separator are left alone: those are
router groups and aliases, and telling a typo from a group the linter has never
heard of is not possible without booting the whole application.

### The markup

| Rule | Severity | What it means |
|---|---|---|
| `tag.malformed` | error | The tag body does not parse, and the generator drops it without a word |
| `tag.empty` | warning | A tag with nothing after it |
| `tag.callable-misplaced` | warning | The `[method]` form on a tag other than `@input` |
| `tag.template-misplaced` | warning | The `{Template}` form on a tag other than `@output` |
| `tag.unknown-template` | error | `@input @Model` / `@output @Model[]` names a template that is not defined |
| `tag.callable-missing` | error | `@input [method]` names a method the controller lacks |
| `tag.unknown-type` | warning | A type outside the known set — it silently becomes `string` |
| `tag.duplicate-variable` | warning | The same variable declared twice; the last one wins |
| `tag.orphan-nesting` | warning | `$address.city` without a declared `$address` |
| `tag.nesting-type-mismatch` | warning | The parent of `$tags[].id` is declared something other than `array` |
| `response.malformed` | error | `@response` does not parse |
| `response.unknown-template` | error | `@response 200 {Name}` names a template that is not defined |
| `response.impossible-code` | error | A status code outside 100–599 |
| `response.duplicate-code` | warning | Two answers for one code; the last one wins |
| `security.unknown-scheme` | error | `@security Name`, or an action-level `security` key, naming a scheme that is not defined |
| `template.unknown-ref` | error | A template field refers through `@Other` to a template that is not defined |
| `default.unknown-variable`, `example.unknown-variable` | warning | A default or example for a variable that has no `@input`, so the value is ignored |
| `default.malformed`, `example.malformed` | error | The tag body does not parse |

Inputs contributed by middleware are taken into account: a `@default` for a
variable one of them declares is legitimate and is not reported.

## Reading the report

```
v1 · user.update
  error    The action points at App\Api\Controllers\UserController::updte(), and there is no such method.  [action.missing-method]
           At runtime this answers 404 — the same 404 as a wrong URL, which is why it goes unnoticed.
  warning  @input $role: the type `enum` is unknown and becomes `string`.  [tag.unknown-type]
           Known types: string, file, number, integer, boolean, array, object.
```

The address is the endpoint — `version · controller.action` — not a file and a
line: the markup for one endpoint is spread across the controller's docblock,
the route map of the Api class and the middleware chain, so "which endpoint is
broken" is the question worth answering.

Every finding carries a stable rule slug, so two reports can be diffed between
runs.

## In code

The linter is a service; the command is a thin wrapper over it.

```php
use Dskripchenko\LaravelApi\Services\Linter\OpenApiLinter;

$issues = app(OpenApiLinter::class)->lint();            // every registered version
$issues = app(OpenApiLinter::class)->lint('v1');        // just one

// Or an explicit map, bypassing the registered module:
$issues = app(OpenApiLinter::class)->lintVersionList(['v1' => MyApi::class]);

foreach ($issues as $issue) {
    $issue->severity;   // 'error' | 'warning'
    $issue->rule;       // 'action.missing-method'
    $issue->where;      // 'v1 · user.update'
    $issue->message;
    $issue->hint;
}
```

## See also

- [Docblock tags reference](docblock-tags.md)
- [Cookbook](cookbook.md)
