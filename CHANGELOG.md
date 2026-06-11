# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[5.1.0]: https://github.com/dskripchenko/laravel-api/compare/5.0.0...5.1.0
[5.0.0]: https://github.com/dskripchenko/laravel-api/compare/4.3.0...5.0.0
