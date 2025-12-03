<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed essential demo data required by the automated tests.
     */
    public function run(): void
    {
        $category = Category::first();
        $unit = Unit::first();
        $outlets = Outlet::all();

        if (!$category || !$unit || $outlets->isEmpty()) {
            $this->command?->warn('DemoDataSeeder skipped: missing category, unit, or outlets.');
            return;
        }

        $productsData = [
            [
                'name' => 'Nasi Goreng Spesial',
                'sku' => 'SKU-NSG-001',
                'barcode' => '899000000001',
                'description' => 'Menu nasi goreng favorit pelanggan',
                'purchase_price' => 15000,
                'selling_price' => 25000,
                'wholesale_price' => 22000,
                'min_stock' => 5,
            ],
            [
                'name' => 'Es Teh Manis',
                'sku' => 'SKU-EST-002',
                'barcode' => '899000000002',
                'description' => 'Minuman dingin menyegarkan',
                'purchase_price' => 3000,
                'selling_price' => 8000,
                'wholesale_price' => 6000,
                'min_stock' => 10,
            ],
            [
                'name' => 'Kopi Hitam',
                'sku' => 'SKU-KOP-003',
                'barcode' => '899000000003',
                'description' => 'Kopi hitam tanpa gula',
                'purchase_price' => 5000,
                'selling_price' => 12000,
                'wholesale_price' => 10000,
                'min_stock' => 8,
            ],
        ];

        $products = collect();

        foreach ($productsData as $data) {
            $products->push(
                Product::updateOrCreate(
                    ['sku' => $data['sku']],
                    array_merge($data, [
                        'category_id' => $category->id,
                        'unit_id' => $unit->id,
                        'is_active' => true,
                    ])
                )
            );
        }

        foreach ($products as $product) {
            foreach ($outlets as $index => $outlet) {
                ProductStock::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'outlet_id' => $outlet->id,
                    ],
                    [
                        'quantity' => 40 + (($index + 1) * 5),
                    ]
                );
            }
        }

        if (Customer::count() === 0) {
            Customer::factory()->count(5)->create();
        }
    }
}

