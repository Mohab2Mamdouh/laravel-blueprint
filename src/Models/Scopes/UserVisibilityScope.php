<?php

namespace BlueprintPhp\Laravel\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class UserVisibilityScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     * Override this in your application to add user-specific filtering logic.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user) {
            return; // skip for unauthenticated requests
        }

        // Add your user-visibility filtering logic here.
    }
}
