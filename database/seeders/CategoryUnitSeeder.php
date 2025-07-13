<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Unit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategoryUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Categories
        $categories = [
            [
                'name' => 'Makanan',
                'description' => 'Kategori untuk produk makanan',
                'is_active' => true,
            ],
            [
                'name' => 'Minuman',
                'description' => 'Kategori untuk produk minuman',
                'is_active' => true,
            ],
            [
                'name' => 'Snack',
                'description' => 'Kategori untuk produk snack dan cemilan',
                'is_active' => true,
            ],
            [
                'name' => 'Elektronik',
                'description' => 'Kategori untuk produk elektronik',
                'is_active' => true,
            ],
            [
                'name' => 'Peralatan Rumah Tangga',
                'description' => 'Kategori untuk peralatan rumah tangga',
                'is_active' => true,
            ],
            [
                'name' => 'Kesehatan & Kecantikan',
                'description' => 'Kategori untuk produk kesehatan dan kecantikan',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }

        // Create Units
        $units = [
            ['name' => 'Pieces', 'symbol' => 'pcs'],
            ['name' => 'Kilogram', 'symbol' => 'kg'],
            ['name' => 'Gram', 'symbol' => 'gr'],
            ['name' => 'Liter', 'symbol' => 'ltr'],
            ['name' => 'Mililiter', 'symbol' => 'ml'],
            ['name' => 'Meter', 'symbol' => 'm'],
            ['name' => 'Centimeter', 'symbol' => 'cm'],
            ['name' => 'Box', 'symbol' => 'box'],
            ['name' => 'Pack', 'symbol' => 'pack'],
            ['name' => 'Dozen', 'symbol' => 'dzn'],
        ];

        foreach ($units as $unit) {
            Unit::create($unit);
        }
    }
}
