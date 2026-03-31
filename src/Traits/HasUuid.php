<?php

namespace Mohab\LaravelBlueprint\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasUuid
{
    // -------------------------------------------------------------------------
    // Boot
    // -------------------------------------------------------------------------

    protected static function bootHasUuid(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->{$model->uuidColumn()})) {
                $model->{$model->uuidColumn()} = (string) Str::uuid();
            }
        });
    }

    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    /**
     * The column that stores the UUID.
     * Override in your model or declare a $uuidColumn property.
     */
    protected function uuidColumn(): string
    {
        return $this->uuidColumn ?? 'uuid';
    }

    // -------------------------------------------------------------------------
    // Query scopes
    // -------------------------------------------------------------------------

    /**
     * Scope: find a model by its UUID or return null.
     *
     * Usage:  Product::findByUuid($uuid);
     */
    public static function findByUuid(string $uuid): ?static
    {
        return static::where((new static)->uuidColumn(), $uuid)->first();
    }

    /**
     * Scope: find a model by its UUID or throw ModelNotFoundException.
     *
     * Usage:  Product::findByUuidOrFail($uuid);
     */
    public static function findByUuidOrFail(string $uuid): static
    {
        return static::where((new static)->uuidColumn(), $uuid)->firstOrFail();
    }

    /**
     * Query scope for inline use inside a query chain.
     *
     * Usage:  Product::query()->whereUuid($uuid)->with('category')->first();
     */
    public function scopeWhereUuid(Builder $query, string $uuid): Builder
    {
        return $query->where($this->uuidColumn(), $uuid);
    }

    // -------------------------------------------------------------------------
    // Accessor
    // -------------------------------------------------------------------------

    public function getUuidAttribute(): string
    {
        return $this->{$this->uuidColumn()};
    }
}