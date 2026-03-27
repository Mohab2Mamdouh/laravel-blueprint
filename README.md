# mohab2mamdouh/laravel-starter

A Laravel starter package that provides a complete foundation for rapid application development. Install once, reuse everywhere.

---

## Contents

| Component | Description |
|-----------|-------------|
| `AddRepositoryPatternCommand` | Artisan command to scaffold versioned Repository/Service/Controller pattern into a module |
| `GenerateSeed` | Artisan command to run unapplied seeders from `database/seeders/RunningSeeder` |
| `MakeSeederCommand` | Artisan command to create timestamp-prefixed seeders |
| Stubs | Stub templates used by `AddRepositoryPatternCommand` |
| `QueryReturnType` | Enum for controlling repository query return types |
| `Responser` | Static helper for consistent JSON API responses |
| `SetLocale` | Middleware to set the application locale from the session |
| `PaginationCollection` | JSON resource collection with standardised pagination metadata |
| Setting (migration, model, controller, service, interface, repo, request) | Full settings module out of the box |
| `BaseModel` | Base Eloquent model with soft deletes, human dates, translatable fallback, and actor tracking |
| `Repository` (contract + eloquent base) | Abstract repository with 20+ methods for clean data access |
| Traits | `Actor`, `Enumable`, `HumanDates`, `TranslatableFallback` |
| `FormattedDateTime` | Eloquent cast for locale-aware formatted date strings |

---

## Requirements

- PHP ^8.2
- Laravel ^11.0 or ^12.0
- `spatie/laravel-translatable` ^6.0

---

## Installation

### Option A — Local path (recommended during development)

1. Copy or symlink this package into your project (e.g. `packages/mohab2mamdouh/laravel-starter`).

2. Register the path repository in your project's `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "packages/mohab2mamdouh/laravel-starter"
    }
],
"require": {
    "mohab2mamdouh/laravel-starter": "*"
}
```

3. Run:

```bash
composer require mohab2mamdouh/laravel-starter
```

### Option B — Packagist

```bash
composer require mohab2mamdouh/laravel-starter
```

The package is auto-discovered by Laravel. The `StarterServiceProvider` is registered automatically.

---

## Migrations

Run the package migrations to create the `seeders` and `settings` tables:

```bash
php artisan migrate
```

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

Seeder files should follow the naming convention `TIMESTAMP^ClassName.php`, e.g.:

```
1704067200^CreateAdminUser.php
2026-01-25^SeedPermissions.php
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

### SetLocale Middleware

Sets the application locale from the `locale` session key. Defaults to `config('app.locale')`.

Register in `bootstrap/app.php` (Laravel 11+):

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'set.locale' => \Mohab\LaravelBlueprint\Http\Middleware\SetLocale::class,
    ]);
})
```

Supported locales default to `['en', 'ar']`. Override via config:

```php
// config/starter.php
return [
    'supported_locales' => ['en', 'ar', 'fr'],
];
```

### PaginationCollection

A JSON resource collection that returns standardised pagination metadata:

```php
use Mohab\LaravelBlueprint\Http\Resources\PaginationCollection;

// Without transformation
return new PaginationCollection($paginatedModels);

// With a resource class
return new PaginationCollection($paginatedModels, UserResource::class);
```

Response shape:

```json
{
  "items": [...],
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
- **Model** — `Mohab\LaravelBlueprint\Models\Setting`
- **Repository Interface** — `SettingRepositoryInterface` (bound to `SettingRepository` via the service provider)
- **Controller** — `SettingController` with `edit`, `update`, and `createBackup` actions
- **Service** — `SettingService` for business logic
- **Request** — `SettingRequest` (validation rules pulled dynamically from the `settings` table)

### BaseModel

Extend `BaseModel` to get soft deletes, human-readable dates, translatable fallback, and actor tracking out of the box:

```php
use Mohab\LaravelBlueprint\Models\BaseModel;

class Product extends BaseModel
{
    protected $guarded = [];
    public array $translatable = ['name', 'description'];
}
```

### Repository Base

Extend the abstract `Repository` class to implement your data-access layer:

```php
use Mohab\LaravelBlueprint\Repository\Eloquent\Repository;
use App\Repository\Contracts\ProductRepositoryInterface;

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
use Mohab\LaravelBlueprint\Traits\Actor;

class Post extends Model
{
    use Actor;
}

$post->creator; // BelongsTo → User
```

#### Enumable

Adds `values()` and `fromName()` helpers to PHP enums:

```php
use Mohab\LaravelBlueprint\Traits\Enumable;

enum Status: string
{
    use Enumable;

    case Active = 'active';
    case Inactive = 'inactive';
}

Status::values();        // ['active', 'inactive']
Status::fromName('Active'); // Status::Active
```

#### HumanDates

Formats `created_at`, `updated_at`, and `email_verified_at` as locale-aware human-readable strings (e.g. `27 Mar, 2026, 03:45 PM`):

```php
use Mohab\LaravelBlueprint\Traits\HumanDates;

class Post extends Model
{
    use HumanDates;
}

$post->created_at; // "27 Mar, 2026, 03:45 PM"
```

#### TranslatableFallback

Extends `spatie/laravel-translatable` with automatic fallback to the first available language when the requested locale is missing:

```php
use Mohab\LaravelBlueprint\Traits\TranslatableFallback;

class Category extends Model
{
    use TranslatableFallback;

    public array $translatable = ['name'];
}
```

### FormattedDateTime Cast

Apply locale-aware date formatting to any model attribute via an Eloquent cast:

```php
use Mohab\LaravelBlueprint\Casts\FormattedDateTime;

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

## License

MIT
