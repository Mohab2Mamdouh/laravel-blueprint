# Changelog

All notable changes to `blueprint-php/laravel` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [3.0.0] - 2026-11-14

### Changed
- Requires PHP 8.5.12 or newer.
- Supports Laravel 13 as well as Laravel 12 (Testbench 11 / PHPUnit 12 for the test suite).

## [2.0.0] - 2026-11-14

First public release as `blueprint-php/laravel` (previously the private `mohab/laravel-blueprint`).
The 1.x tags are the legacy `Mohab\LaravelBlueprint` code — don't install them.

### Added
- Platform split: `PlatformService`, `PlatformRequest`, `PlatformResource`, `HasPlatform`,
  `Platform` and `Version` enums, and `PlatformBindingServiceProvider`.
- Generators: `make:platform-service`, `make:platform-request`, `make:platform-resource`,
  `make:repo` and `make:action`.
- Tracked seeders: `seed`, `seed:rollback`, `seed:reset` and `seed:status`, with optional
  per-tenant runs on `stancl/tenancy`.
- Middleware: `Localize`, `RequestContext`, `SetDefaultPlatform` and `ValidatePlatformVersion`.
- Request-context logging (`TapRequestContext`, `RequestContextProcessor`), and `dbg()` with
  `DebugException`.
- `AcceptedScopes`, `HasFilterableScopes`, `Sortable`, `ScopeableRegistry` and the
  `UniqueTranslatable` rule.
- `ProvidesSettings` contract and the `blueprint.settings.repository` config key for `getSettings()`.
- `blueprint.seeders.default_target` config key.
- Publishable stubs (`blueprint-stubs`); published copies override the package's own.
- Publishable tracked-seeders migration (`blueprint-migrations`).
- Test suite and CI.

### Changed
- Namespace `Mohab\LaravelBlueprint` → `BlueprintPhp\Laravel`.
- Requires Laravel 12.
- Migrations are published instead of loaded automatically.
- `Actor` and `BelongsToUser` resolve the user model from `auth.providers.users.model`.

### Removed
- The built-in Settings module (model, repository, service, request). Bring your own and point
  `blueprint.settings.repository` at it.
- `FcmHelper`, `UploadImages` and the `generate:seed` command.
