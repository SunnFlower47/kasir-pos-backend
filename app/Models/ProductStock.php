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

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

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
     * Add stock quantity with atomic update to prevent race conditions
     */
    public function addStock(float|int $quantity, string $type = 'in', ?string $referenceType = null, ?int $referenceId = null, ?string $notes = null): void
    {
        $oldQuantity = $this->quantity;

        // Use atomic database increment to prevent race conditions
        \Illuminate\Support\Facades\DB::table('product_stocks')
            ->where('id', $this->id)
            ->increment('quantity', $quantity);

        // Reload to get updated quantity
        $this->refresh();
        $newQuantity = $this->quantity;

        // Create stock movement record
        StockMovement::create([
            'product_id' => $this->product_id,
            'outlet_id' => $this->outlet_id,
            'type' => $type,
            'quantity' => $quantity,
            'quantity_before' => $oldQuantity,
            'quantity_after' => $newQuantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'user_id' => Auth::id(),
        ]);
    }

    /**
     * Reduce stock quantity with atomic update to prevent race conditions
     */
    public function reduceStock(float|int $quantity, string $type = 'out', ?string $referenceType = null, ?int $referenceId = null, ?string $notes = null): bool
    {
        // Use atomic database decrement to prevent race conditions
        $oldQuantity = $this->quantity;

        // Lock the row for update and check if sufficient stock exists
        $affected = \Illuminate\Support\Facades\DB::table('product_stocks')
            ->where('id', $this->id)
            ->where('quantity', '>=', $quantity)
            ->decrement('quantity', $quantity);

        if ($affected === 0) {
            return false; // Insufficient stock or row not found
        }

        // Reload to get updated quantity
        $this->refresh();
        $newQuantity = $this->quantity;

        // Create stock movement record
        StockMovement::create([
            'product_id' => $this->product_id,
            'outlet_id' => $this->outlet_id,
            'type' => $type,
            'quantity' => -$quantity,
            'quantity_before' => $oldQuantity,
            'quantity_after' => $newQuantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'user_id' => Auth::id(),
        ]);

        return true;
    }
}
