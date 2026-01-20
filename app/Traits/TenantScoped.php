<?php

namespace App\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait TenantScoped
{
    /**
     * The "booted" method of the model.
     */
    protected static function bootTenantScoped(): void
    {
        // Global Scope: Filter by Tenant
        static::addGlobalScope('tenant', function (Builder $builder) {
            // Use hasUser() to avoid infinite recursion if the User model itself uses this trait.
            // Auth::check() triggers retrieval, which triggers this scope, which triggers Auth::check()...
            if (Auth::hasUser()) {
                $user = Auth::user();

                // If user has tenant_id, filter by it
                if ($user->tenant_id) {
                    $builder->where($builder->getModel()->getTable() . '.tenant_id', $user->tenant_id);
                }
            }
        });

        // Auto-assign tenant_id on creation
        static::creating(function (Model $model) {
            if (Auth::check() && Auth::user()->tenant_id) {
                $model->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    /**
     * Get the tenant that owns the model.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
