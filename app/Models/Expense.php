<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory, Auditable, \App\Traits\TenantScoped;

    protected $fillable = [
        'tenant_id',
        'expense_number',
        'outlet_id',
        'expense_date',
        'category',
        'description',
        'amount',
        'payment_method',
        'notes',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * Get the outlet that owns the expense.
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * Get the user that owns the expense.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate unique expense number
     */
    public static function generateExpenseNumber(): string
    {
        $prefix = 'EXP';
        $date = date('Ymd');
        $lastExpense = self::where('expense_number', 'like', "{$prefix}-{$date}-%")
            ->orderBy('expense_number', 'desc')
            ->first();

        if ($lastExpense) {
            $lastNumber = (int) substr($lastExpense->expense_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $date, $newNumber);
    }
}
