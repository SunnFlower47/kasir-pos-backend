<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'product_id',
        'quantity',
        'unit_price',
        'purchase_price',
        'discount_amount',
        'total_price',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_price' => 'decimal:2',
            'quantity' => 'decimal:3',
            'transaction_id' => 'integer',
            'product_id' => 'integer',
        ];
    }

    /**
     * Get the transaction that owns the transaction item.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Get the product that owns the transaction item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculate total price for this item
     */
    public function calculateTotalPrice(): void
    {
        $total = ($this->unit_price * $this->quantity) - $this->discount_amount;
        $this->setAttribute('total_price', $total);
    }
}
