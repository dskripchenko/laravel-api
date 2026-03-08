# Test Coverage Report

**Package:** dskripchenko/laravel-api
**Date:** 2026-03-08
**PHP:** 8.5.3 | **Pest:** latest | **Coverage driver:** PCOV
**Tests:** 269 passed (539 assertions)

## Summary

| Metric | Value |
|--------|-------|
| **Total coverage** | **90.5%** |
| Classes with 100% | 18 / 28 |
| Classes below 80% | 4 / 28 |

## Coverage by component

| Component | Coverage | Uncovered lines |
|-----------|----------|-----------------|
| Components/BaseApi | 93.6% | 111-112, 190, 230, 240, 283, 287 |
| Components/BaseModule | 85.7% | 83, 116-117 |
| Components/Meta | 100.0% | — |
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
| Providers/ApiServiceProvider | 95.7% | 74, 105 |
| Providers/BaseServiceProvider | 100.0% | — |
| Requests/BaseApiRequest | 97.5% | 60 |
| Requests/CrudSearchRequest | 100.0% | — |
| Resources/BaseJsonResource | 76.9% | 24, 26, 48 |
| Resources/BaseJsonResourceCollection | 100.0% | — |
| Services/ApiResponseHelper | 100.0% | — |
| Services/CrudService | 84.9% | 67, 72, 86, 118, 236-252 |
| Traits/OpenApiTrait | 94.7% | 93, 96, 101, 128, 211-212, 502-505, 520-521, 661-662, 678-680, 722-731, 810, 852, 855, 894, 897, 1099 |
| Traits/Testing/MakesHttpApiRequests | 58.8% | 94, 112-144 |

## Areas with low coverage

### Console/Commands/ApiInstall — 61.2%
Artisan `api:install` command. Low coverage because it performs file system operations (creating directories, copying stubs) that require integration testing in a real Laravel app context.

### Console/Commands/BaseCommand — 57.1%
Abstract base command with helper methods. Not directly instantiated in tests.

### Traits/Testing/MakesHttpApiRequests — 58.8%
Testing trait used by consumers of the package. The assertion helpers (`assertApiSuccess`, `assertApiError`, `assertApiValidationError`) are tested indirectly through feature tests but some branches remain uncovered.

### Resources/BaseJsonResource — 76.9%
Resource serialization with conditional logic. Some edge cases in `toArray()` not exercised.

## HTML Report

Full interactive HTML report: `coverage-report/index.html`

Open in browser:
```bash
open coverage-report/index.html        # macOS
xdg-open coverage-report/index.html    # Linux
```
