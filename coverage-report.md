# Test Coverage Report

**Package:** dskripchenko/laravel-api
**Date:** 2026-03-20
**PHP:** 8.5.4 | **Pest:** latest | **Coverage driver:** PCOV
**Tests:** 310 passed (648 assertions)

## Summary

| Metric | Value |
|--------|-------|
| **Total coverage** | **89.6%** |
| Classes with 100% | 18 / 30 |
| Classes below 80% | 4 / 30 |

## Coverage by component

| Component | Coverage | Uncovered lines |
|-----------|----------|-----------------|
| Components/BaseApi | 93.6% | 111-112, 190, 230, 240, 283, 287 |
| Components/BaseModule | 90.5% | 83, 115-117, 143, 159 |
| Components/Meta | 100.0% | — |
| Console/Commands/ApiGenerateTypes | 0.0% | (entire file — artisan command tested only for registration) |
| Console/Commands/ApiInstall | 61.2% | 22-32, 82-119 |
| Console/Commands/BaseCommand | 57.1% | 23-30 |
| Controllers/ApiController | 100.0% | — |
| Controllers/ApiDocumentationController | 95.5% | 46 |
| Controllers/CrudController | 100.0% | — |
| Exceptions/ApiErrorHandler | 100.0% | — |
| Exceptions/ApiException | 100.0% | — |
| Exceptions/Handler | 100.0% | — |
| Facades/ApiErrorHandler | 100.0% | — |
| Facades/ApiModule | 100.0% | — |
| Facades/ApiRequest | 100.0% | — |
| Interfaces/ApiInterface | 100.0% | — |
| Interfaces/CrudServiceInterface | 100.0% | — |
| Middlewares/ApiMiddleware | 100.0% | — |
| Middlewares/RequestIdMiddleware | 100.0% | — |
| Providers/ApiServiceProvider | 96.2% | 76, 107 |
| Providers/BaseServiceProvider | 100.0% | — |
| Requests/BaseApiRequest | 97.5% | 60 |
| Requests/CrudSearchRequest | 100.0% | — |
| Resources/BaseJsonResource | 76.9% | 24, 26, 48 |
| Resources/BaseJsonResourceCollection | 100.0% | — |
| Services/ApiResponseHelper | 100.0% | — |
| Services/CrudService | 84.9% | 67, 72, 86, 118, 236-252 |
| Services/OpenApiTypeScriptGenerator | 94.4% | 34, 71-72, 76-77, 161, 256 |
| Traits/OpenApiTrait | 94.8% | 93, 96, 101, 128, 211-212, 516-519, 534-535, 674-676, 692-694, 736-745, 824, 866, 869, 908, 911, 1116 |
| Traits/Testing/MakesHttpApiRequests | 58.8% | 94, 112-144 |

## Changes since last report (2026-03-08)

| Change | Detail |
|--------|--------|
| Tests | 269 → 310 (+41) |
| Assertions | 539 → 648 (+109) |
| Total coverage | 90.5% → 89.6% (−0.9%) |
| New: Services/OpenApiTypeScriptGenerator | 94.4% |
| New: Console/Commands/ApiGenerateTypes | 0.0% (registration-only tests) |
| Updated: Components/BaseModule | 85.7% → 90.5% (+4.8%) — getRouteDefinitions() covered |
| Updated: Providers/ApiServiceProvider | 95.7% → 96.2% (+0.5%) — named route registration |

Coverage decreased slightly because `ApiGenerateTypes` command's `handle()` method requires a fully bootstrapped Laravel app with API module — tested only for registration (signature, artisan binding).

## Areas with low coverage

### Console/Commands/ApiGenerateTypes — 0.0%
Artisan `api:generate-types` command. The `handle()` method requires a real Laravel app with a configured API module and filesystem. Signature and registration are tested. The underlying `OpenApiTypeScriptGenerator` service is tested at 94.4%.

### Console/Commands/ApiInstall — 61.2%
Artisan `api:install` command. Low coverage because it performs file system operations (creating directories, copying stubs) that require integration testing in a real Laravel app context.

### Console/Commands/BaseCommand — 57.1%
Abstract base command with interactive `askValid()` loop. Not directly instantiated in tests.

### Traits/Testing/MakesHttpApiRequests — 58.8%
Testing trait used by consumers of the package. The assertion helpers are tested indirectly through feature tests but some branches remain uncovered.

### Resources/BaseJsonResource — 76.9%
Resource serialization with conditional logic. Some edge cases in `toArray()` not exercised.

## HTML Report

Full interactive HTML report: `coverage-report/index.html`

Open in browser:
```bash
open coverage-report/index.html        # macOS
xdg-open coverage-report/index.html    # Linux
```
