<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Supplier;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'PT Sumber Makmur',
                'contact_person' => 'Budi Santoso',
                'phone' => '021-12345678',
                'email' => 'budi@sumbermakmur.com',
                'address' => 'Jl. Raya Jakarta No. 123, Jakarta Pusat',
                'is_active' => true,
            ],
            [
                'name' => 'CV Mitra Jaya',
                'contact_person' => 'Siti Rahayu',
                'phone' => '021-87654321',
                'email' => 'siti@mitrajaya.com',
                'address' => 'Jl. Sudirman No. 456, Jakarta Selatan',
                'is_active' => true,
            ],
            [
                'name' => 'UD Berkah Sejahtera',
                'contact_person' => 'Ahmad Wijaya',
                'phone' => '021-11223344',
                'email' => 'ahmad@berkahsejahtera.com',
                'address' => 'Jl. Gatot Subroto No. 789, Jakarta Barat',
                'is_active' => false, // Test inactive supplier
            ],
            [
                'name' => 'PT Global Supply',
                'contact_person' => 'Maria Gonzales',
                'phone' => '021-55667788',
                'email' => 'maria@globalsupply.com',
                'address' => 'Jl. Thamrin No. 321, Jakarta Pusat',
                'is_active' => true,
            ],
            [
                'name' => 'CV Mandiri Utama',
                'contact_person' => 'Rudi Hartono',
                'phone' => '021-99887766',
                'email' => 'rudi@mandiriutama.com',
                'address' => 'Jl. Kuningan No. 654, Jakarta Selatan',
                'is_active' => false, // Test inactive supplier
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::create($supplier);
        }
    }
}
