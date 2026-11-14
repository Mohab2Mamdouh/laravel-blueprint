<?php

namespace BlueprintPhp\Laravel\Traits;

use Illuminate\Support\Str;

trait HasSlug
{
    // -------------------------------------------------------------------------
    // Boot
    // -------------------------------------------------------------------------

    protected static function bootHasSlug(): void
    {
        static::creating(function ($model): void {
            // Respect a manually pre-set slug.
            if (empty($model->{$model->slugColumn()})) {
                $model->generateAndSetSlug();
            }
        });

        static::updating(function ($model): void {
            // Only regenerate when the source field actually changed.
            if ($model->isDirty($model->slugSource())) {
                $model->generateAndSetSlug();
            }
        });
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Force-regenerate the slug and persist it immediately.
     */
    public function regenerateSlug(): void
    {
        $this->generateAndSetSlug();
        $this->save();
    }

    // -------------------------------------------------------------------------
    // Overridable configuration
    // -------------------------------------------------------------------------

    /**
     * The model attribute whose value is turned into a slug.
     * Override this method (or declare a $slugFrom property) in your model.
     */
    protected function slugSource(): string
    {
        return $this->slugFrom ?? 'name';
    }

    /**
     * The model attribute where the generated slug is stored.
     * Override this method (or declare a $slugTo property) in your model.
     */
    protected function slugColumn(): string
    {
        return $this->slugTo ?? 'slug';
    }

    /**
     * Extract the raw string value to slugify from the model.
     *
     * Handles both plain attributes and spatie/laravel-translatable attributes
     * transparently — no hard dependency on the package.
     */
    protected function slugSourceValue(): string
    {
        $source = $this->slugSource();

        // spatie/laravel-translatable exposes getTranslation(); use it when available.
        if (
            method_exists($this, 'getTranslation') &&
            method_exists($this, 'isTranslatableAttribute') &&
            $this->isTranslatableAttribute($source)
        ) {
            return (string) $this->getTranslation($source, app()->getLocale())
                ?: (string) $this->getTranslation($source, config('app.fallback_locale', 'en'));
        }

        return (string) ($this->getAttribute($source) ?? '');
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function generateAndSetSlug(): void
    {
        $base = Str::slug($this->slugSourceValue());

        $this->{$this->slugColumn()} = $this->makeSlugUnique($base);
    }

    private function makeSlugUnique(string $base): string
    {
        $slug = $base;
        $column = $this->slugColumn();
        $hasSoftDeletes = in_array(
            'Illuminate\Database\Eloquent\SoftDeletes',
            class_uses_recursive($this),    // ← recursive: catches traits on parent classes too
            true,
        );

        while ($this->slugExists($slug, $column, $hasSoftDeletes)) {
            // Append a short random suffix and re-slugify to keep the result clean.
            $slug = Str::slug($base.'-'.Str::random(4));
        }

        return $slug;
    }

    private function slugExists(string $slug, string $column, bool $hasSoftDeletes): bool
    {
        $query = static::where($column, $slug);

        if ($hasSoftDeletes) {
            $query->withTrashed();
        }

        // On update, exclude the current record from the uniqueness check.
        if ($this->exists) {
            $query->where($this->getKeyName(), '!=', $this->getKey());
        }

        return $query->exists();
    }
}
