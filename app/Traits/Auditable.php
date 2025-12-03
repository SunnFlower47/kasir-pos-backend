<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    /**
     * Store original data temporarily for audit logging
     */
    protected ?array $originalAuditData = null;

    /**
     * Boot the trait and register event listeners
     */
    public static function bootAuditable(): void
    {
        // Log when model is created
        static::created(function ($model) {
            $values = $model->toArray();
            // Remove sensitive fields
            unset($values['password'], $values['password_confirmation']);
            $model->logAudit('created', null, $values);
        });

        // Log when model is updated
        static::updating(function ($model) {
            // Store original data before update
            $model->originalAuditData = $model->getOriginal();
        });

        static::updated(function ($model) {
            $oldValues = $model->originalAuditData ?? $model->getOriginal();
            $newValues = $model->getChanges();
            
            // Merge current attributes for full context
            $currentAttributes = $model->toArray();
            $finalNewValues = array_merge($currentAttributes, $newValues);
            
            // Remove sensitive fields
            unset($finalNewValues['password'], $finalNewValues['password_confirmation']);
            if (is_array($oldValues)) {
                unset($oldValues['password'], $oldValues['password_confirmation']);
            }
            
            $model->logAudit('updated', $oldValues, $finalNewValues);
            // Clear stored data
            $model->originalAuditData = null;
        });

        // Log when model is deleted
        static::deleting(function ($model) {
            // Store original data before delete
            $model->originalAuditData = $model->getOriginal();
        });

        static::deleted(function ($model) {
            $oldValues = $model->originalAuditData ?? [];
            // Remove sensitive fields
            if (is_array($oldValues)) {
                unset($oldValues['password'], $oldValues['password_confirmation']);
            }
            $model->logAudit('deleted', $oldValues, null);
        });
    }

    /**
     * Log audit entry
     */
    protected function logAudit(string $event, ?array $oldValues = null, ?array $newValues = null): void
    {
        try {
            AuditLog::create([
                'model_type' => get_class($this),
                'model_id' => $this->getKey(),
                'event' => $event,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'user_id' => Auth::id(),
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        } catch (\Exception $e) {
            // Silently fail to avoid breaking the application
            \Log::error('Failed to create audit log: ' . $e->getMessage());
        }
    }

    /**
     * Get audit logs for this model
     */
    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'model');
    }
}

