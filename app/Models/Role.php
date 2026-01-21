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
        'scope', // system, tenant
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

        // NOTE: Tenant isolation is REMOVED for Roles.
        // Roles are now GLOBAL templates available to all tenants.
        
        // CACHE INVALIDATION: Force clear Spatie cache on any Role change
        $flushCache = function ($role) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        };

        static::created($flushCache);
        static::updated($flushCache);
        static::deleted($flushCache);
    }
}
