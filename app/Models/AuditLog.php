<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    use HasFactory;
    use \App\Traits\TenantScoped;

    protected $fillable = [
        'model_type',
        'model_id',
        'event',
        'old_values',
        'new_values',
        'user_id',
        'ip_address',
        'user_agent',
        'client_platform',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'json',
            'new_values' => 'json',
        ];
    }

    /**
     * Get the user that owns the audit log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the owning model.
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Create audit log entry
     */
    public static function createLog(string $modelType, int $modelId, string $event, ?array $oldValues = null, ?array $newValues = null): void
    {
        self::create([
            'model_type' => $modelType,
            'model_id' => $modelId,
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'user_id' => Auth::id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            // Ensure tenant_id is captured if we are acting on a Tenant scope or if user has one
            // For System Admins impacting a Tenant, we ideally valid logging visible to that Tenant?
            // Or just System Log? The user said "Audit Logs", likely system wide.
            // If TenantScoped trait is active, it might force Auth::user()->tenant_id.
            // We'll let the Trait handle it, but if System Admin (tenant_id null), 
            // the log might end up with null tenant_id, which is correct for System Logs.
        ]);
    }
}
