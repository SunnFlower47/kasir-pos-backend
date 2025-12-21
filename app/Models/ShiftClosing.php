<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftClosing extends Model
{
    use HasFactory;

    protected $fillable = [
        'outlet_id',
        'user_id',
        'cashier_name',
        'cashier_email',
        'closing_date',
        'closing_time',
        'total_transactions',
        'total_revenue',
        'revenue_by_payment',
        'last_closing_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'closing_date' => 'date',
            'closing_time' => 'datetime',
            'total_transactions' => 'integer',
            'total_revenue' => 'decimal:2',
            'revenue_by_payment' => 'array',
            'last_closing_at' => 'datetime',
            'outlet_id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    /**
     * Get the outlet that owns the shift closing.
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * Get the user that owns the shift closing.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
