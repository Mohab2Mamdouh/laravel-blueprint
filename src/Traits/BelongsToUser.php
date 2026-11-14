<?php

namespace BlueprintPhp\Laravel\Traits;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToUser
{
    protected static function bootBelongsToUser(): void
    {
        static::creating(function ($model) {
            if (auth()->check() && empty($model->user_id)) {
                $model->user_id = auth()->id();
            }
        });

        static::deleting(function ($model) {
            if (auth()->check() && $model->user_id !== auth()->id()) {
                abort(403, 'Unauthorized action.');
            }
        });

        static::addGlobalScope('byUser', function (Builder $query) {
            if (auth()->check()) {
                $query->where('user_id', auth()->id());
            }
        });
    }

    /**
     * Get the user that owns the model.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            config('auth.providers.users.model', 'App\\Models\\User'),
            'user_id',
        );
    }

    #[Scope]
    protected function byUser(Builder $query, $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
