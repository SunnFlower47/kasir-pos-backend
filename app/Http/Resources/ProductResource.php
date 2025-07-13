<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'description' => $this->description,
            'category' => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ],
            'unit' => [
                'id' => $this->unit->id,
                'name' => $this->unit->name,
                'symbol' => $this->unit->symbol,
            ],
            'purchase_price' => $this->purchase_price,
            'selling_price' => $this->selling_price,
            'wholesale_price' => $this->wholesale_price,
            'min_stock' => $this->min_stock,
            'image' => $this->image,
            'is_active' => $this->is_active,
            'stock_quantity' => $this->when(isset($this->stock_quantity), $this->stock_quantity),
            'is_low_stock' => $this->when(isset($this->is_low_stock), $this->is_low_stock),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
