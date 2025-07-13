<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class ProductStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'outlet_id',
        'quantity',
    ];

    /**
     * Get the product that owns the product stock.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the outlet that owns the product stock.
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * Add stock quantity
     */
    public function addStock(int $quantity, string $type = 'in', ?string $referenceType = null, ?int $referenceId = null, ?string $notes = null): void
    {
        $oldQuantity = $this->quantity;
        $this->quantity += $quantity;
        $this->save();

        // Create stock movement record
        StockMovement::create([
            'product_id' => $this->product_id,
            'outlet_id' => $this->outlet_id,
            'type' => $type,
            'quantity' => $quantity,
            'quantity_before' => $oldQuantity,
            'quantity_after' => $this->quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'user_id' => Auth::id(),
        ]);
    }

    /**
     * Reduce stock quantity
     */
    public function reduceStock(int $quantity, string $type = 'out', ?string $referenceType = null, ?int $referenceId = null, ?string $notes = null): bool
    {
        if ($this->quantity < $quantity) {
            return false; // Insufficient stock
        }

        $oldQuantity = $this->quantity;
        $this->quantity -= $quantity;
        $this->save();

        // Create stock movement record
        StockMovement::create([
            'product_id' => $this->product_id,
            'outlet_id' => $this->outlet_id,
            'type' => $type,
            'quantity' => -$quantity,
            'quantity_before' => $oldQuantity,
            'quantity_after' => $this->quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'user_id' => Auth::id(),
        ]);

        return true;
    }
}
