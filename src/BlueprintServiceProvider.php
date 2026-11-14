<?php

namespace BlueprintPhp\Laravel;

use BlueprintPhp\Laravel\Commands\AddRepositoryPatternCommand;
use BlueprintPhp\Laravel\Commands\MakeAction;
use BlueprintPhp\Laravel\Commands\MakePlatformRequest;
use BlueprintPhp\Laravel\Commands\MakePlatformResource;
use BlueprintPhp\Laravel\Commands\MakePlatformService;
use BlueprintPhp\Laravel\Commands\MakeRepo;
use BlueprintPhp\Laravel\Commands\MakeSeederCommand;
use BlueprintPhp\Laravel\Commands\SeedCommand;
use BlueprintPhp\Laravel\Commands\SeedResetCommand;
use BlueprintPhp\Laravel\Commands\SeedRollbackCommand;
use BlueprintPhp\Laravel\Commands\SeedStatusCommand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class BlueprintServiceProvider extends ServiceProvider
{
    /**
     * Merge the package config.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/blueprint.php', 'blueprint'
        );
    }

    /**
     * Bootstrap package services by delegating to small methods.
     */
    public function boot(): void
    {
        $this->bootConfig();
        $this->bootMigrations();
        $this->bootStubs();
        $this->bootTranslations();
        $this->bootCommands();
        $this->bootViews();
        $this->bootShortcuts();
    }

    private function bootConfig(): void
    {
        $this->publishes([
            __DIR__.'/../config/blueprint.php' => config_path('blueprint.php'),
        ], 'blueprint-config');

        // Auto-publish config if enabled and the file doesn't exist in the app.
        // This makes the package more plug-and-play, but can be disabled via config('blueprint.auto_publish')
        try {
            if (config('blueprint.auto_publish', true) && ! file_exists(config_path('blueprint.php'))) {
                (new Filesystem)->copy(__DIR__.'/../config/blueprint.php', config_path('blueprint.php'));
            }
        } catch (\Throwable $e) {
            // Fail silently if running in an environment without Laravel helpers available yet.
        }
    }

    /**
     * The tracked-seeders table is published, not auto-loaded: an app may keep it
     * in a non-default migrations folder (e.g. one per tenant database).
     */
    private function bootMigrations(): void
    {
        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'blueprint-migrations');
    }

    /**
     * Publish the generator stubs to `stubs/blueprint`. Published copies take
     * precedence over the package's own (see {@see self::stubPath()}).
     */
    private function bootStubs(): void
    {
        $this->publishes([
            __DIR__.'/../stubs' => base_path('stubs/blueprint'),
        ], 'blueprint-stubs');
    }

    /**
     * Absolute path of a generator stub: the app's published copy in
     * `stubs/blueprint/` when present, otherwise the package's own.
     */
    public static function stubPath(string $stub): string
    {
        $published = base_path('stubs/blueprint/'.$stub);

        return is_file($published) ? $published : dirname(__DIR__).'/stubs/'.$stub;
    }

    private function bootTranslations(): void
    {
        // Load package translations so they can be referenced with the "blueprint::" namespace
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'blueprint');
        // In case you have JSON translations in the lang folder as well
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');

        // Allow applications to publish package translation files to their resources/lang/vendor/blueprint
        $this->publishes([
            __DIR__.'/../lang' => resource_path('lang/vendor/blueprint'),
        ], 'blueprint-lang');

        // Auto-publish translations if enabled and the directory doesn't exist in the app.
        try {
            if (config('blueprint.auto_publish', true) && ! is_dir(resource_path('lang/vendor/blueprint'))) {
                (new Filesystem)->ensureDirectoryExists(resource_path('lang/vendor/blueprint'));
                (new Filesystem)->copyDirectory(__DIR__.'/../lang', resource_path('lang/vendor/blueprint'));
            }
        } catch (\Throwable $e) {
            // Fail silently when resource_path or filesystem is not available yet.
        }
    }

    private function bootCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AddRepositoryPatternCommand::class,
                SeedCommand::class,
                SeedRollbackCommand::class,
                SeedResetCommand::class,
                SeedStatusCommand::class,
                MakeSeederCommand::class,
                MakePlatformService::class,
                MakePlatformRequest::class,
                MakePlatformResource::class,
                MakeRepo::class,
                MakeAction::class,
            ]);
        }
    }

    /**
     * Share application settings with every view for backward compatibility with
     * projects that reference `$websiteSettings` in Blade.
     *
     * This delegates to the global getSettings() helper, so it is resolved lazily
     * and cached — it will not query (or crash) before the settings table exists,
     * and only hits the database once per cache lifetime.
     *
     * New code should prefer calling getSettings() directly instead of relying on
     * this shared variable.
     */
    private function bootViews(): void
    {
        View::share('websiteSettings', getSettings());
    }

    private function bootShortcuts(): void
    {
        // -------------------------------------------------------------------------
        // Blueprint (migration) macros
        // -------------------------------------------------------------------------

        // Foreign key to users — column name is configurable for polymorphic scenarios
        Blueprint::macro('foreignId', function (string $column = 'user_id', $table = 'users') {
            return $this->foreignId($column)
                ->constrained($table)
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        // Status string with default value and index
        Blueprint::macro('status', function (string $default = 'active') {
            return $this->string('status')->default($default)->index();
        });

        // Soft deletes + who deleted the record
        Blueprint::macro('softDeletesWithUser', function ($table = 'users') {
            $this->softDeletes();
            $this->foreignId('deleted_by')->nullable()->constrained($table)->nullOnDelete();
        });

        // Audit columns — who created and last updated the record
        Blueprint::macro('audit', function ($table = 'users') {
            $this->foreignId('created_by')->nullable()->constrained($table)->nullOnDelete();
            $this->foreignId('updated_by')->nullable()->constrained($table)->nullOnDelete();
        });

        // Audit columns — who created and last updated the record
        Blueprint::macro('fullAudit', function ($table = 'users') {
            $this->foreignId('created_by')->nullable()->constrained($table)->nullOnDelete();
            $this->foreignId('updated_by')->nullable()->constrained($table)->nullOnDelete();
            $this->foreignId('deleted_by')->nullable()->constrained($table)->nullOnDelete();
        });

        // Unique indexed slug
        Blueprint::macro('slug', function () {
            return $this->string('slug')->unique()->index();
        });

        // Nullable JSON meta bag
        Blueprint::macro('meta', function () {
            return $this->json('meta')->nullable();
        });

        // UUID column — unique, indexed, not nullable
        Blueprint::macro('uuid', function (string $column = 'uuid') {
            return $this->string($column, 36)->unique()->index();
        });

        // Nullable phone number
        Blueprint::macro('phone', function (string $column = 'phone') {
            return $this->string($column, 20)->nullable();
        });

        // Decimal price column
        Blueprint::macro('price', function (string $column = 'price', int $total = 10, int $places = 2) {
            return $this->decimal($column, $total, $places)->default(0);
        });

        // Unsigned integer for drag-and-drop ordering
        Blueprint::macro('ordering', function (string $column = 'sort_order') {
            return $this->unsignedInteger($column)->default(0)->index();
        });

        // Nullable, indexed timestamp for scheduled publishing
        Blueprint::macro('publishedAt', function () {
            return $this->timestamp('published_at')->nullable()->index();
        });

        // -------------------------------------------------------------------------
        // Builder (query) macros
        // -------------------------------------------------------------------------

        // Active / inactive scopes — align with HasStatus defaults
        Builder::macro('active', function (string $column = 'status') {
            return $this->where($column, 'active');
        });

        Builder::macro('inactive', function (string $column = 'status') {
            return $this->where($column, 'inactive');
        });

        // Multi-column LIKE search
        Builder::macro('search', function (string $term, array $columns) {
            return $this->where(function ($q) use ($term, $columns) {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', "%{$term}%");
                }
            });
        });

        // Sort by ordering column
        Builder::macro('ordered', function (string $column = 'sort_order') {
            return $this->orderBy($column);
        });

        // Filter to rows that are published (published_at is set and in the past)
        Builder::macro('published', function () {
            return $this->whereNotNull('published_at')
                ->where('published_at', '<=', now());
        });

        // Filter to scheduled rows (published_at is in the future)
        Builder::macro('scheduled', function () {
            return $this->whereNotNull('published_at')
                ->where('published_at', '>', now());
        });

        // Case-insensitive keyword search across a model's searchable columns
        // (getSearchableColumns() / $searchableColumns; falls back to name/title).
        Builder::macro('byKeywords', function ($term) {
            return $this->where(function ($q) use ($term) {
                $model = $this->getModel();

                $columns = match (true) {
                    method_exists($model, 'getSearchableColumns') && $model->getSearchableColumns() => $model->getSearchableColumns(),
                    property_exists($model, 'searchableColumns') && $model->searchableColumns => $model->searchableColumns,
                    default => ['name', 'title'],
                };

                $term = strtolower(trim((string) $term));
                $existing = Schema::getColumnListing($model->getTable());

                foreach ($columns as $column) {
                    if (in_array($column, $existing, true)) {
                        $q->orWhere(DB::raw("LOWER(`{$column}`)"), 'LIKE', "%{$term}%");
                    }
                }
            });
        });

        // Force an empty result set.
        Builder::macro('empty', function () {
            return $this->whereRaw('1 = 0');
        });
    }
}
