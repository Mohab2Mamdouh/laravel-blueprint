# mohab/laravel-blueprint

A Laravel blueprint package that provides a complete foundation for rapid application development. Install once, reuse everywhere.

---

## Contents

| Section | Description |
|---------|-------------|
| Service Provider | Consolidated `BlueprintServiceProvider` (bootstraps config, migrations, translations, commands, views, and registers macros) |
| Commands | Artisan commands: `module:add-repository`, `generate:seed`, `make:seeder` |
| Configuration | `config/blueprint.php` and the package auto-publish behaviour |
| Translations | Package translations under `src/lang` (namespaced `blueprint`) and publishing instructions |
| Migrations | Package migrations (seeders table, settings table) |
| Components | `QueryReturnType`, `Responser`, `LogHelper`, `PaginationCollection`, `FormattedDateTime` cast |
| Settings Module | Migration, Model, Repository, Controller, Service, Request for application settings |
| BaseModel & Traits | `BaseModel` and traits: `Actor`, `Enumable`, `HumanDates`, `TranslatableFallback`, `HasSlug`, `UploadImages` |
| Repository Base | Repository contract + Eloquent base implementation for data access |
| Stubs | Scaffolding stubs used by `AddRepositoryPatternCommand` |
| Database shortcuts & macros | Blueprint and Builder macros registered by the provider (e.g. `userId`, `audit`, `search`) |
| Usage & Examples | Integration snippets, middleware alias, and resource helpers |
| Troubleshooting & License | Notes on auto-publish, troubleshooting, and license (MIT) |

---

## Requirements

- PHP ^8.3
- Laravel ^11.0 or ^12.0
- `spatie/laravel-translatable` ^6.0

---

## Installation

### Option A — Local path (recommended during development)

1. Copy or symlink this package into your project (e.g. `packages/mohab/laravel-blueprint`).

2. Register the path repository in your project's `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "packages/mohab/laravel-blueprint"
    }
  ],
  "require": {
    "mohab/laravel-blueprint": "*"
  }
}
```

3. Run:

```bash
composer require mohab/laravel-blueprint
```

### Option B — Private GitHub Repository

Add the repository source to your project's `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/Mohab2Mamdouh/laravel-blueprint"
    }
  ]
}
```

Then run:

```bash
composer require mohab/laravel-blueprint
```

> A GitHub personal access token with **Contents read-only** permission on this repository is required.
> Store it via the `COMPOSER_AUTH` environment variable:
>
> ```env
> COMPOSER_AUTH={"github-oauth":{"github.com":"your-token-here"}}
> ```

The package is auto-discovered by Laravel. The `BlueprintServiceProvider` is registered automatically.

Note on providers
-----------------

The package consolidates its functionality into a single service provider: `Mohab\\LaravelBlueprint\\BlueprintServiceProvider` (registered via composer auto-discovery). Internally the provider delegates bootstrap responsibilities to small private methods such as `bootConfig()`, `bootMigrations()`, `bootTranslations()`, `bootCommands()`, and `bootViews()`.

This simplifies maintenance and makes integration predictable for consuming applications — you only need to worry about a single provider being present.

---

## Migrations

Run the package migrations to create the `seeders` and `settings` tables (and any future migrations provided by the package):

```bash
php artisan migrate
```

---

## Configuration

The package works out of the box with sensible defaults. To publish the config file and override them:

```bash
php artisan vendor:publish --tag=blueprint-config
```

This creates `config/blueprint.php` in your project. Example:

```php
return [
    'supported_locales' => ['en', 'ar'],
    'auto_publish' => true,

    // Optional: size limit when LogHelper truncates exception traces
    'logging' => [
        'max_trace_length' => 5000,
    ],

    // View / cache related options used by the provider's view composer
    'cache' => [
        // null = use provider default (!app()->environment('local'))
        'enabled' => null,
        // cache key used when storing resolved website settings
        'website_settings_key' => 'view.websiteSettings',
        // when true the package uses Cache::rememberForever
        'remember_forever' => true,
    ],
];
```

Auto-publish
------------

The package can auto-publish some of its files into the consuming application on first boot to make the package more plug-and-play. This behaviour is intended for convenience during initial integration and is enabled by default.

What is auto-published
- `config/blueprint.php` => copied to your application's `config/` directory (when missing)
- Translation files from `src/lang` => copied to `resources/lang/vendor/blueprint` (when the directory is missing)

How it works
- During provider boot the package checks `config('blueprint.auto_publish', true)`. When true and the target files/directories do not exist in the application, the package will attempt to copy the config and translation files into the app. The operation is safe and wrapped in try/catch blocks — if file system access is not available or writing fails the provider will continue without throwing errors.

Configuration keys
- `blueprint.auto_publish` (bool) — enable/disable the auto-publish behaviour. Default: `true`.
- `blueprint.supported_locales` (array) — locales supported by middleware and helpers. Default: `['en', 'ar']`.
- `blueprint.logging.max_trace_length` (int) — maximum characters to keep from exception traces when using the package `LogHelper`. Default: `5000`.
 - `blueprint.cache.enabled` (bool|null) — control whether the package uses cache for the `websiteSettings` view binding. When `null` the provider uses its default behaviour (disable caching in `local` environment). Default: `null`.
 - `blueprint.cache.website_settings_key` (string) — cache key used for website settings stored by the provider. Default: `view.websiteSettings`.
 - `blueprint.cache.remember_forever` (bool) — when true the package uses `Cache::rememberForever` to persist settings; otherwise it will call `Cache::remember` (consumers must provide a TTL when `false`). Default: `true`.

Disabling auto-publish

1. Publish the config into your application so you can control package behaviour manually:

```bash
php artisan vendor:publish --tag=blueprint-config
```

2. Edit `config/blueprint.php` and set:

```php
'auto_publish' => false,
```

Manual publishing

Alternatively use Laravel's `vendor:publish` to selectively publish package assets. The package registers the following publish tags:

- `blueprint-config` — publishes `config/blueprint.php`
- `blueprint-lang` — publishes translations from `src/lang` to `resources/lang/vendor/blueprint`

Examples:

```bash
# Publish config only
php artisan vendor:publish --tag=blueprint-config

# Publish translations only
php artisan vendor:publish --tag=blueprint-lang

# Publish by provider (both tags)
php artisan vendor:publish --provider="Mohab\\LaravelBlueprint\\BlueprintServiceProvider"
```

---

## Translations

The package ships translations under `src/lang`. Translations are loaded with the `blueprint` namespace so you can reference them safely from your app without colliding with other packages.

- Namespaced PHP translation usage (recommended):

```php
// In PHP
__('blueprint::public.hello');

// In Blade
@lang('blueprint::public.hello')
```

- Publish translations to override them in the application:

```bash
php artisan vendor:publish --provider="Mohab\\LaravelBlueprint\\BlueprintServiceProvider" --tag=blueprint-lang
```

- JSON translations: If you provide JSON translation files in `src/lang`, they will be loaded via `loadJsonTranslationsFrom` and can be used with `__('some_key')`. JSON keys are global (not namespaced) and therefore more likely to collide — prefer namespaced PHP translation files when possible.

- Validation messages: Laravel's validator reads `validation.php` from the app locale directory by default. If you provide `validation.php` in the package, consumers should publish it to the app to make the messages available as app-level defaults. Alternatively, reference package-namespaced validation keys directly in FormRequest `messages()`.

If you want to override package translation keys, publish them with the `blueprint-lang` tag and edit the files under `resources/lang/vendor/blueprint` in your application.

---

## Artisan Commands

### `module:add-repository`

Scaffold a versioned Repository/Service/Interface/Controller into an existing [nwidart/laravel-modules](https://nwidart.com/laravel-modules) module.

```bash
# Basic stubs (v1)
php artisan module:add-repository Product

# Specific version
php artisan module:add-repository Product --ver=2

# Custom pattern name
php artisan module:add-repository Product --name=Item

# CRUD stubs
php artisan module:add-repository Product --crud

# Also generate a model
php artisan module:add-repository Product --crud --model
```

**Options:**

| Option | Default | Description |
|--------|---------|-------------|
| `--ver` | `1` | Version number for the generated files |
| `--name` | module name | Custom name for the Repository/Service/Interface |
| `--crud` | false | Use CRUD stubs (getAll, getById, create, update, delete) |
| `--model` | false | Generate a model file alongside the pattern |

### `generate:seed`

Run all unapplied seeders from `database/seeders/RunningSeeder`. Seeders are tracked in the `seeders` table and executed in timestamp order.

```bash
php artisan generate:seed
```

Seeder files must follow the naming convention `TIMESTAMP_ClassName.php`, e.g.:

```
1704067200_CreateAdminUser.php
1706227200_SeedPermissions.php
```

### `make:seeder`

Create a new timestamp-prefixed seeder in `database/seeders/RunningSeeder`:

```bash
php artisan make:seeder CreateAdminUser
# Creates: database/seeders/RunningSeeder/1704067200_CreateAdminUser.php
```

---

## Components

### QueryReturnType Enum

Controls how a repository query returns results:

```php
use Mohab\LaravelBlueprint\Enums\QueryReturnType;

// Returns an Eloquent Collection
$repository->query(returnType: QueryReturnType::GET);

// Returns the first matching Model or null
$repository->query(returnType: QueryReturnType::FIRST);

// Returns a LengthAwarePaginator
$repository->query(returnType: QueryReturnType::PAGINATE, perPage: 15);

// Returns the underlying Builder for further chaining
$query = $repository->query(returnType: QueryReturnType::QUERY);
$results = $query->where('status', 'active')->get();
```

### Responser Helper

Static helper for building consistent JSON API responses:

```php
use Mohab\LaravelBlueprint\Helpers\Responser;

// 200 success
return Responser::success(200, 'User created', $user);

// 422 validation error
return Responser::fail(422, 'Validation failed', $errors);

// Log the exception
return Responser::fail(500, 'Something went wrong', [], $exception);

// Custom status code
return Responser::custom(201, 'Resource created', $data);
```

### LogHelper

A small wrapper around Laravel's logger that formats messages consistently and appends request/auth context automatically. Useful for logging caught exceptions and enriching logs with request metadata.

Usage examples:

```php
use Mohab\\LaravelBlueprint\\Helpers\\LogHelper;

LogHelper::info('Order processed', ['order_id' => $id]);

try {
    // ... something that throws
} catch (\\Throwable $e) {
    LogHelper::exception('PaymentService: charge failed', $e, ['order_id' => $id]);
}
```

What it adds
- Consistent event header formatting (e.g. "=== [PaymentService: charge failed] ===").
- Automatically includes request metadata: `url`, `method`, `ip`, and `user_id` (nullable when not available).
- Truncates long exception traces according to `blueprint.logging.max_trace_length` to avoid bloating logs.

### SetLocale Middleware

Sets the application locale from the `locale` session key. Defaults to `config('app.locale')`.

Register in `bootstrap/app.php` (Laravel 11+):

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'set.locale' => \Mohab\\LaravelBlueprint\\Http\\Middleware\\SetLocale::class,
    ]);
})
```

Supported locales default to `['en', 'ar']`. Override via `config/blueprint.php`:

```php
return [
    'supported_locales' => ['en', 'ar', 'fr'],
];
```

### PaginationCollection

A JSON resource collection that returns standardised pagination metadata:

```php
use Mohab\\LaravelBlueprint\\Http\\Resources\\PaginationCollection;

// Without transformation
return new PaginationCollection($paginatedModels);

// With a resource class
return new PaginationCollection($paginatedModels, UserResource::class);
```

Response shape:

```json
{
  "items": [],
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 72,
    "from": 1,
    "to": 15
  }
}
```

### Settings

The package ships a full settings module:

- **Migration** — creates the `settings` table with `key`, `value` (JSON), `validation` (JSON), timestamps, and soft deletes.
- **Model** — `Mohab\\LaravelBlueprint\\Models\\Setting`
- **Repository Interface** — `SettingRepositoryInterface` (bound to `SettingRepository` via the service provider)
- **Controller** — `SettingController` with `edit`, `update`, and `createBackup` actions
- **Service** — `SettingService` for business logic
- **Request** — `SettingRequest` (validation rules pulled dynamically from the `settings` table)

### BaseModel

Extend `BaseModel` to get soft deletes, human-readable dates, translatable fallback, and actor tracking out of the box:

```php
use Mohab\\LaravelBlueprint\\Models\\BaseModel;

class Product extends BaseModel
{
    protected $guarded = [];
    public array $translatable = ['name', 'description'];
}
```

### Repository Base

Extend the abstract `Repository` class to implement your data-access layer:

```php
use Mohab\\LaravelBlueprint\\Repository\\Eloquent\\Repository;
use App\\Repository\\Contracts\\ProductRepositoryInterface;

class ProductRepository extends Repository implements ProductRepositoryInterface
{
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }
}
```

Available methods: `query`, `getAll`, `getActive`, `getById`, `get`, `first`, `getFirst`, `getByUuid`, `getByIds`, `create`, `update`, `updateOrCreate`, `insert`, `createMany`, `delete`, `forceDelete`, `restore`, `paginate`, `paginateWithQuery`, `sync`, `whereHasMorph`, `touch`, `firstOrCreate`.

### Traits

#### Actor

Automatically sets `created_by` to the authenticated user's ID on model creation, and provides a `creator()` relationship:

```php
use Mohab\\LaravelBlueprint\\Traits\\Actor;

class Post extends Model
{
    use Actor;
}

$post->creator; // BelongsTo → User
```

#### Enumable

Adds `values()` and `fromName()` helpers to PHP enums:

```php
use Mohab\\LaravelBlueprint\\Traits\\Enumable;

enum Status: string
{
    use Enumable;

    case Active = 'active';
    case Inactive = 'inactive';
}

Status::values();           // ['active', 'inactive']
Status::fromName('Active'); // Status::Active
```

#### HumanDates

Formats `created_at`, `updated_at`, and `email_verified_at` as locale-aware human-readable strings (e.g. `27 Mar, 2026, 03:45 PM`):

```php
use Mohab\\LaravelBlueprint\\Traits\\HumanDates;

class Post extends Model
{
    use HumanDates;
}

$post->created_at; // "27 Mar, 2026, 03:45 PM"
```

#### TranslatableFallback

Extends `spatie/laravel-translatable` with automatic fallback to the first available language when the requested locale is missing:

```php
use Mohab\\LaravelBlueprint\\Traits\\TranslatableFallback;

class Category extends Model
{
    use TranslatableFallback;

    public array $translatable = ['name'];
}
```

### FormattedDateTime Cast

Apply locale-aware date formatting to any model attribute via an Eloquent cast:

```php
use Mohab\\LaravelBlueprint\\Casts\\FormattedDateTime;

class Order extends Model
{
    protected $casts = [
        'shipped_at' => FormattedDateTime::class,
    ];
}

$order->shipped_at; // "27 Mar, 2026, 03:45 PM"
```

---

## Stubs

The stubs used by `AddRepositoryPatternCommand` are located at `src/Commands/stubs/`. Available stubs:

| Stub | Description |
|------|-------------|
| `interface.stub` | Basic repository interface |
| `interface-crud.stub` | CRUD repository interface |
| `repository.stub` | Basic repository class |
| `repository-crud.stub` | CRUD repository with implementations |
| `service.stub` | Basic service class |
| `service-crud.stub` | CRUD service with all methods |
| `controller.stub` | Basic controller |
| `controller-crud.stub` | CRUD controller (index, store, show, update, destroy) |
| `model.stub` | Eloquent model extending `BaseModel` |
| `seeder.stub` | Anonymous seeder class template |

All stubs support three placeholder variables:

| Placeholder | Description |
|-------------|-------------|
| `{{ ModuleName }}` | The module name (StudlyCase) |
| `{{ PatternName }}` | The custom pattern/entity name (StudlyCase) |
| `{{ Version }}` | The version number |

---

## Database shortcuts & macros

The service provider registers several helpful Blueprint and Builder macros that you can use in your migrations and query builders:

- `Blueprint::userId()` — shorthand for a constrained `user_id` foreign id with cascade on delete/update
- `Blueprint::status($default = 'active')` — string status column with default and index
- `Blueprint::softDeletesWithUser()` — soft deletes plus `deleted_by` foreign id
- `Blueprint::audit()` — sets `created_by` and `updated_by` foreign ids (nullable)
- `Blueprint::slug()` — unique indexed `slug` column
- `Blueprint::meta()` — nullable JSON `meta` column

Builder macros:
- `Builder::active($column = 'status')` — scope to only active rows
- `Builder::inactive($column = 'status')` — scope to only inactive rows
- `Builder::search($term, $columns)` — multi-column LIKE search helper

These are registered in the provider method `bootShortCuts()`.

---

## Usage examples

- Register the package's middleware alias (example in `bootstrap/app.php` for Laravel 11+):

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'set.locale' => \Mohab\\LaravelBlueprint\\Http\\Middleware\\SetLocale::class,
    ]);
})
```

- Use a repository:

```php
$products = resolve(App\\Repository\\Contracts\\ProductRepositoryInterface::class)->getAll();
```

- Use the PaginationCollection in a controller:

```php
return new PaginationCollection(Product::paginate(15), ProductResource::class);
```

---

## Troubleshooting & notes

- Auto-publish is a convenience feature. If you're developing multiple environments (CI, build servers, containers), prefer publishing the config and translations explicitly via `vendor:publish` and commit the resulting files to your app repository if needed.
- The provider's auto-publish logic is defensive and will not throw in early bootstrap contexts where file helper functions may not be available.

---

## License

MIT