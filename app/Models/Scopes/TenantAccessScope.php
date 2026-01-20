<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantAccessScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (Auth::hasUser()) {
            $user = Auth::user();

            if ($user->tenant_id) {
                // User is a Tenant User
                $builder->where(function ($query) use ($user) {
                    // 1. Must be scoped for tenants (not system-only roles)
                    $query->where('scope', 'tenant')
                          // 2. AND (Belong to their tenant OR be a global template)
                          ->where(function ($q) use ($user) {
                              $q->where('tenant_id', $user->tenant_id)
                                ->orWhereNull('tenant_id');
                          });
                });
            } else {
                // User is System Admin (tenant_id is NULL)
                // Can see everything, but maybe we want to sort or scope?
                // For now, let System Admin see all.
            }
        }
    }
}
