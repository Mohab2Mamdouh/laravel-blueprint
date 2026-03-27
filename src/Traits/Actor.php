<?php

namespace Mohab2Mamdouh\LaravelStarter\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait Actor
{
    protected static function bootActor(): void
    {
        $user = Auth::user();

        static::creating(function ($model) use ($user) {
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
        return $this->belongsTo(config('auth.providers.users.model', \App\Models\User::class), 'created_by');
    }
}
