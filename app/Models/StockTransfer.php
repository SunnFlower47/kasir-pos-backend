<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_number',
        'from_outlet_id',
        'to_outlet_id',
        'transfer_date',
        'status',
        'notes',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'transfer_date' => 'date',
        ];
    }

    /**
     * Get the from outlet that owns the stock transfer.
     */
    public function fromOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'from_outlet_id');
    }

    /**
     * Get the to outlet that owns the stock transfer.
     */
    public function toOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'to_outlet_id');
    }

    /**
     * Get the user that owns the stock transfer.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the stock transfer items for the stock transfer.
     */
    public function stockTransferItems(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    /**
     * Alias for stockTransferItems (for frontend compatibility)
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    /**
     * Generate unique transfer number
     */
    public static function generateTransferNumber(): string
    {
        $date = now()->format('Ymd');
        $lastTransfer = self::whereDate('created_at', now())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastTransfer ?
            (int) substr($lastTransfer->transfer_number, -4) + 1 : 1;

        return 'STF' . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
