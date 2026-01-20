<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory, Auditable, \App\Traits\TenantScoped;

    protected $fillable = [
        'tenant_id',
        'name',
        'sku',
        'barcode',
        'description',
        'category_id',
        'unit_id',
        'purchase_price',
        'selling_price',
        'wholesale_price',
        'min_stock',
        'image',
        'is_active',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'min_stock' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }
        
        if (filter_var($this->image, FILTER_VALIDATE_URL)) {
            return $this->image;
        }

        return url('storage/' . $this->image);
    }

    /**
     * Get the category that owns the product.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the unit that owns the product.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the product stocks for the product.
     */
    public function productStocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    /**
     * Get the stock movements for the product.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get the transaction items for the product.
     */
    public function transactionItems(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    /**
     * Get the purchase items for the product.
     */
    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * Get the stock transfer items for the product.
     */
    public function stockTransferItems(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    /**
     * The promotions that belong to the product.
     */
    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'promotion_products');
    }

    /**
     * Get stock quantity for specific outlet
     */
    public function getStockQuantity(int $outletId): float|int
    {
        $stock = $this->productStocks()->where('outlet_id', $outletId)->first();
        return $stock ? (float) $stock->quantity : 0.0;
    }

    /**
     * Check if product is low stock for specific outlet
     */
    public function isLowStock(int $outletId): bool
    {
        return $this->getStockQuantity($outletId) <= $this->min_stock;
    }

    /**
     * Get the additional units for the product.
     */
    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }
}
