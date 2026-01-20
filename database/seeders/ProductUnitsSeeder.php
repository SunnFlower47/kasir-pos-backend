<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Unit;
use App\Models\ProductUnit;

class ProductUnitsSeeder extends Seeder
{
    public function run()
    {
        // Get first few products
        $products = Product::with('unit')->limit(10)->get();

        foreach ($products as $product) {
            // Create Box unit if not exists
            $boxUnit = Unit::firstOrCreate(
                ['tenant_id' => $product->tenant_id, 'name' => 'Box'],
                ['symbol' => 'Box']
            );

            // Create Karton unit if not exists
            $kartonUnit = Unit::firstOrCreate(
                ['tenant_id' => $product->tenant_id, 'name' => 'Karton'],
                ['symbol' => 'Krt']
            );

            // Add Box unit (1 Box = 24 pcs) if not already added
            ProductUnit::firstOrCreate(
                ['product_id' => $product->id, 'unit_id' => $boxUnit->id],
                [
                    'conversion_factor' => 24,
                    'selling_price' => $product->selling_price * 24,
                    'purchase_price' => $product->purchase_price * 24,
                ]
            );

            // Add Karton unit (1 Karton = 48 pcs) if not already added
            ProductUnit::firstOrCreate(
                ['product_id' => $product->id, 'unit_id' => $kartonUnit->id],
                [
                    'conversion_factor' => 48,
                    'selling_price' => $product->selling_price * 48,
                    'purchase_price' => $product->purchase_price * 48,
                ]
            );

            $this->command->info("Added units for: {$product->name}");
        }

        $this->command->info('Product units seeded successfully!');
    }
}
