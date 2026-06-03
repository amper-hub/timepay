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
            if (!$model->isDirty('company_id') && Auth::check() && Auth::user()->company_id) {
                $model->company_id = Auth::user()->company_id;
            }
        });
    }
}
