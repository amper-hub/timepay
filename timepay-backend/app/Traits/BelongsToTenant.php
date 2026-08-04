<?php

namespace App\Traits;

use App\Models\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    /**
     * Boot the trait for the model.
     */
    public static function bootBelongsToTenant(): void
    {
        // Apply the global tenant scope
        static::addGlobalScope(new TenantScope());

        // Automatically set company_id on model creation
        static::creating(function ($model) {
            if ($model->isDirty('company_id')) {
                return;
            }

            if (! Auth::check()) {
                return;
            }

            $user = Auth::user();

            if ($user->isSuperAdmin()) {
                return;
            }

            if ($user->company_id) {
                $model->company_id = $user->company_id;
            }
        });
    }
}
