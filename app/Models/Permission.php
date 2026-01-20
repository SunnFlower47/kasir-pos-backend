<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected $fillable = [
        'name',
        'guard_name',
        'scope', // 'system', 'tenant'
        'updated_at',
        'created_at',
    ];

    public const SCOPE_SYSTEM = 'system';
    public const SCOPE_TENANT = 'tenant';
}
