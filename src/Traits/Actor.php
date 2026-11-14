<?php

namespace BlueprintPhp\Laravel\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Stamps the audit columns with the authenticated user's id:
 *   created_by (creating), updated_by (updating), deleted_by (soft-deleting).
 *
 * Each stamp is guarded by a per-table column check, so models whose table
 * lacks a given column (e.g. plain softDeletes() without deleted_by) are safely
 * skipped. Opt a model out entirely with `public bool $actor = false;`.
 */
trait Actor
{
    /**
     * Set to false on your model to skip audit tracking entirely.
     *
     *   public bool $actor = false;
     */
    public bool $actor = true;

    /** Per-table cache of audit-column existence: [table][column => bool]. */
    protected static array $actorColumns = [];

    protected static function bootActor(): void
    {
        static::creating(function ($model): void {
            $model->stampActor('created_by');
        });

        static::updating(function ($model): void {
            $model->stampActor('updated_by');
        });

        static::deleting(function ($model): void {
            // Soft-deletes only: stamp who deleted, persisting it quietly before
            // SoftDeletes writes deleted_at. Force-deletes leave no row to stamp.
            if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
                return;
            }

            if ($model->stampActor('deleted_by')) {
                $model->saveQuietly();
            }
        });

        static::restoring(function ($model): void {
            if ($model->actor && $model->actorHasColumn('deleted_by')) {
                $model->deleted_by = null; // included in the restore save
            }
        });
    }

    /**
     * Stamp an audit column with the current user's id when applicable.
     * Returns true if the column was actually set.
     */
    protected function stampActor(string $column): bool
    {
        if (! $this->actor || ! Auth::check() || ! $this->actorHasColumn($column)) {
            return false;
        }

        $this->{$column} = Auth::id();

        return true;
    }

    protected function actorHasColumn(string $column): bool
    {
        $table = $this->getTable();

        return static::$actorColumns[$table][$column] ??= Schema::hasColumn($table, $column);
    }

    /**
     * Get the creator of the model.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            config('auth.providers.users.model', 'App\\Models\\User'),
            'created_by',
        );
    }
}
