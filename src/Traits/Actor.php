<?php

namespace Mohab\LaravelBlueprint\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait Actor
{
    /**
     * Set to false on your model to skip created_by tracking entirely.
     *
     *   public bool $actor = false;
     */
    public bool $actor = true;

    protected static function bootActor(): void
    {
        static::creating(function ($model): void {
            // Respect the per-model opt-out flag.
            if (!$model->actor) {
                return;
            }

            $user = Auth::user();

            if ($user) {
                $model->created_by = $user->id;
            }
        });
    }

    /**
     * Get the creator of the model.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            config('auth.providers.users.model', \App\Models\User::class),
            'created_by',
        );
    }
}