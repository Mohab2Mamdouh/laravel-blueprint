# blueprint-php/laravel

[![tests](https://github.com/Mohab2Mamdouh/laravel-blueprint/actions/workflows/tests.yml/badge.svg)](https://github.com/Mohab2Mamdouh/laravel-blueprint/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/blueprint-php/laravel.svg)](https://packagist.org/packages/blueprint-php/laravel)
[![License](https://img.shields.io/packagist/l/blueprint-php/laravel.svg)](LICENSE)

A layered foundation for Laravel applications. It gives every feature the same shape:

```
Controller (thin) → FormRequest → Service → Repository (interface + Eloquent) → Model → Resource
```

…and ships the pieces that make that shape cheap to repeat: a base repository, platform-split
services/requests/resources (web vs mobile, per API version), a JSON response envelope, tracked
seeders, migration and query macros, model traits, request-context logging, and generators that
scaffold a whole slice in one command.

## Requirements

- PHP 8.3+
- Laravel 12
- [`spatie/laravel-translatable`](https://github.com/spatie/laravel-translatable) 6 (installed automatically)
- Optional: [`stancl/tenancy`](https://tenancyforlaravel.com) 3, to run tracked seeders per tenant database

## Installation

```bash
composer require blueprint-php/laravel
```

The service provider is auto-discovered. Publish what you need:

```bash
php artisan vendor:publish --tag=blueprint-config       # config/blueprint.php
php artisan vendor:publish --tag=blueprint-lang         # lang/vendor/blueprint
php artisan vendor:publish --tag=blueprint-migrations   # the tracked-seeders table
php artisan vendor:publish --tag=blueprint-stubs        # stubs/blueprint (generator templates)
php artisan migrate
```

> With `blueprint.auto_publish` on (the default) the config and translations are copied into the
> app on first boot when missing. Set it to `false` once they are published.

## Contents

| Area | What you get |
|------|--------------|
| **Repository** | `RepositoryInterface` + Eloquent `Repository` base: `query()` with named scopes, `paginate`, `getById`, `getByUuid`, `create`, `update`, `updateOrCreate`, `delete`/`forceDelete`/`restore`, `sync`, … |
| **Platform split** | `PlatformService`, `PlatformRequest`, `PlatformResource`, the `HasPlatform` contract, `Platform` (`web`/`mobile`) and `Version` (`v1`) enums, and `PlatformBindingServiceProvider`, which binds each abstract to the concrete matching the request's `X-Platform` header and URL version |
| **HTTP** | `Responser` (`{ status, message, data }` envelope), `PaginationCollection`, middleware: `SetLocale`, `Localize`, `RequestContext`, `SetDefaultPlatform`, `ValidatePlatformVersion` |
| **Models** | `BaseModel`, `Seeder` model, `UserVisibilityScope`, `FormattedDateTime` cast |
| **Traits** | `Actor`, `AcceptedScopes`, `BelongsToUser`, `Enumable`, `Filterable`, `HasFilterableScopes`, `HasMeta`, `HasSlug`, `HasStatus`, `HasUuid`, `HumanDates`, `Sortable`, `TranslatableFallback` |
| **Scopes** | `ScopeableRegistry`: lists each model's accepted request scopes (for a `GET /scopes/{entity}` discovery endpoint) |
| **Validation** | `UniqueTranslatable` rule (case-insensitive uniqueness within one locale of a translatable column) |
| **Logging** | `TapRequestContext` / `RequestContextProcessor`: stamp platform, user and request id on every log record |
| **Helpers** | `getSettings()`, `dbg()`, `LogHelper`, `CacheHelper`, `MailHelper` |
| **Enums** | `Platform`, `Version`, `QueryReturnType`, `UserStatus`, `SystemStatus` |
| **Commands** | Generators and tracked-seeder commands (below) |
| **Macros** | Schema `Blueprint` and Eloquent `Builder` macros (below) |

## Platform-split services, requests and resources

One feature can serve a web dashboard and a mobile app with different rules and response shapes
while controllers stay platform-agnostic. Controllers type-hint the **abstract**; a provider picks
the **concrete** per request from the `X-Platform` header and the URL version (`api/v1/...`).

```php
use BlueprintPhp\Laravel\Providers\PlatformBindingServiceProvider;

class PlatformServiceProvider extends PlatformBindingServiceProvider
{
    protected function getServiceImplementations(int $version): array
    {
        return match ($version) {
            1 => [
                OrderAbstractService::class => [
                    Web\OrderService::class,     // static platform(): Platform::WEB
                    Mobile\OrderService::class,  // static platform(): Platform::MOBILE
                ],
            ],
            default => [],
        };
    }

    protected function getRequestImplementations(int $version): array { /* same shape */ }

    protected function getResourceImplementations(int $version): array { /* same shape */ }
}
```

Every concrete implements `HasPlatform` (`static platform(): Platform` and `static version(): Version`).
The `make:platform-*` generators write these classes and register them in the provider for you.

## Middleware

The package does not register middleware aliases itself. Add the ones you use in
`bootstrap/app.php`:

```php
use BlueprintPhp\Laravel\Http\Middleware\{Localize, RequestContext, SetDefaultPlatform, SetLocale, ValidatePlatformVersion};

->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'set.locale'       => SetLocale::class,               // web: locale from the session
        'localize'         => Localize::class,                // api: user language → Accept-Language
        'request.context'  => RequestContext::class,          // seed Context for logs and dbg()
        'platform.version' => ValidatePlatformVersion::class, // 400 on a bad version or X-Platform
        'platform.default' => SetDefaultPlatform::class,      // default X-Platform (web) on web routes
    ]);
})
```

Locales are limited to `blueprint.supported_locales`.

## Responses

```php
use BlueprintPhp\Laravel\Helpers\Responser;

return Responser::success(data: new OrderResource($order));
// {"status":200,"message":"Success","data":{...}}

return Responser::fail(status: 422, message: __('Invalid'), throwable: $e); // logs $e
```

## Repositories

```php
use BlueprintPhp\Laravel\Enums\QueryReturnType;

$orders = $this->orders->query(
    scopes: ['byStatus' => 'open', 'byKeywords' => $term],
    relations: ['customer'],
    returnType: QueryReturnType::PAGINATE,
    perPage: 20,
);
```

Request filters are plain `#[Scope]` methods on the model. Add `AcceptedScopes` to a model to fix
which scopes a request may apply, and in what order. Global `Builder` macros such as `byKeywords`
also work as repository scopes.

## Settings

`getSettings()` reads application settings once and caches them forever:

```php
getSettings();            // ['site_name' => '…', …]
getSettings('site_name'); // a single value, or null
```

Point it at any class (or container-bound interface) with `settings(): array`, for example by
implementing `BlueprintPhp\Laravel\Contracts\ProvidesSettings` on your settings repository:

```php
// config/blueprint.php
'settings' => [
    'repository' => App\Repository\Contracts\SettingRepositoryInterface::class,
    'table' => 'settings',
],
```

Forget the `blueprint.cache.website_settings_key` cache key when settings change. With no
repository configured, `getSettings()` returns `[]`. Every view also receives the settings as
`$websiteSettings`.

## Tracked seeders

Seeders that run **once**, recorded in a `seeders` table and grouped into batches, like migrations.
Seeder files live in `database/seeders/central` (or `database/seeders/tenant`).

```bash
php artisan make:seeder CreateDefaultRoles   # timestamp-prefixed seeder
php artisan seed                             # run the unapplied ones as a new batch
php artisan seed:status
php artisan seed:rollback                    # undo the last batch
php artisan seed:reset
```

With `stancl/tenancy` installed, `--tenant` runs the command in every tenant database and
`--tenant=<id>` in one. `--central` forces the central database. The default target is
`blueprint.seeders.default_target` (env `SEEDER_TARGET`).

Publish the table first: `php artisan vendor:publish --tag=blueprint-migrations`.

## Generators

| Command | Generates |
|---------|-----------|
| `make:platform-service Order --crud --platform=web,mobile` | Model, repository (interface + Eloquent + binding), abstract + per-platform services, controllers, request and resource |
| `make:platform-request SignIn --actor=User --domain=Auth` | Abstract + per-platform FormRequests, registered in the platform provider |
| `make:platform-resource Order` | Abstract + per-platform API resources, registered in the platform provider |
| `make:repo Order --model=App\\Models\\Order` | Repository interface + Eloquent implementation, bound in `RepositoryServiceProvider` |
| `make:action Order/CreateOrder` | An action class in `app/Actions` |
| `module:add-repository Order --crud` | A versioned Service + Repository + Interface (+ controller) |

Common options: `--actor=` and `--domain=` choose the folders, `--api-version=` the version, and
`--platform=web,mobile` the platforms. Run any command with `--help` for the full list.

To customise the templates, publish them (`--tag=blueprint-stubs`) and edit `stubs/blueprint/*.stub`.
A published stub always takes precedence over the package's own copy.

## Macros

**Migrations (`Illuminate\Database\Schema\Blueprint`)**

| Macro | Columns |
|-------|---------|
| `$table->audit()` | `created_by`, `updated_by` (nullable FKs to `users`) |
| `$table->fullAudit()` | + `deleted_by` |
| `$table->softDeletesWithUser()` | `deleted_at` + `deleted_by` |
| `$table->status('active')` | indexed `status` string |
| `$table->slug()` | unique indexed `slug` |
| `$table->meta()` | nullable JSON `meta` |
| `$table->phone()` | nullable `phone` (20 chars) |
| `$table->price()` | `decimal(10, 2)` default 0 |
| `$table->ordering()` | indexed `sort_order` |
| `$table->publishedAt()` | nullable indexed `published_at` |

**Queries (`Illuminate\Database\Eloquent\Builder`)**

`active()`, `inactive()`, `search($term, $columns)`, `ordered()`, `published()`, `scheduled()`,
`byKeywords($term)` (over the model's `$searchableColumns`), `empty()`.

## Request-context logging

```php
// config/logging.php
'daily' => [
    'driver' => 'daily',
    'path' => storage_path('logs/laravel.log'),
    'tap' => [BlueprintPhp\Laravel\Logging\TapRequestContext::class],
],
```

With the `request.context` middleware on a route group, every log line carries the platform, user
and request id. `dbg($value)` throws a `DebugException` that renders the value, the caller trace and
that context as JSON.

## Testing

```bash
composer install
composer test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

MIT. See [LICENSE](LICENSE).
