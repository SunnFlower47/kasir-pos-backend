<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'description',
        'discount_value',
        'discount_type',
        'min_purchase',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'min_purchase' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The products that belong to the promotion.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promotion_products');
    }

    /**
     * Check if promotion is currently active
     */
    public function isCurrentlyActive(): bool
    {
        return $this->is_active &&
               $this->start_date <= now() &&
               $this->end_date >= now();
    }

    /**
     * Calculate discount amount for given total
     */
    public function calculateDiscount(float $total): float
    {
        if ($total < $this->min_purchase) {
            return 0;
        }

        return match ($this->discount_type) {
            'percentage' => $total * ($this->discount_value / 100),
            'fixed' => min($this->discount_value, $total),
            default => 0,
        };
    }
}
