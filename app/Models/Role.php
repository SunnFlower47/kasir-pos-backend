<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'tenant_id',
        'scope',
        'description',
        'updated_at',
        'created_at',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Scope: Tenant Isolation
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (Auth::check()) {
                $user = Auth::user();
                
                if ($user->hasRole('System Admin')) {

                } elseif ($user->tenant_id) {
                    // Strict Isolation: Tenants only see their own roles.
                    // Global templates are cloned at registration, so no need to show them here.
                    $builder->where('tenant_id', $user->tenant_id);
                }
            }
        });

        // Auto-assign tenant_id on creation
        static::creating(function ($role) {
            if (Auth::check()) {
               $user = Auth::user();
               if ($user->tenant_id && !$role->tenant_id) {
                   $role->tenant_id = $user->tenant_id;
               }
            }
        });

        // CACHE INVALIDATION: Force clear Spatie cache on any Role change
        $flushCache = function ($role) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        };

        static::created($flushCache);
        static::updated($flushCache);
        static::deleted($flushCache);
    }
}
