<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * Prevents infinite loop by:
     * 1. Skipping scope during console operations (migrations, artisan commands)
     * 2. Safely checking if user is loaded before accessing company_id
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Skip tenant filtering when running in console (migrations, tinker, artisan commands, etc.)
        // This prevents infinite loop during authentication process
        if (app()->runningInConsole()) {
            return;
        }

        // Only apply tenant filtering if a user is authenticated and fully loaded
        // auth()->hasUser() checks without triggering a new database query
        if (auth()->hasUser() && auth()->user()->company_id) {
            $builder->where('company_id', auth()->user()->company_id);
        }
    }
}
