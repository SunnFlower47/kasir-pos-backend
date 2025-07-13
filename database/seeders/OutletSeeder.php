<?php

namespace Database\Seeders;

use App\Models\Outlet;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OutletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $outlets = [
            [
                'name' => 'Outlet Pusat',
                'code' => 'OUT001',
                'address' => 'Jl. Sudirman No. 123, Jakarta Pusat',
                'phone' => '021-12345678',
                'email' => 'pusat@kasirpos.com',
                'is_active' => true,
            ],
            [
                'name' => 'Outlet Cabang Utara',
                'code' => 'OUT002',
                'address' => 'Jl. Gajah Mada No. 456, Jakarta Utara',
                'phone' => '021-87654321',
                'email' => 'utara@kasirpos.com',
                'is_active' => true,
            ],
            [
                'name' => 'Outlet Cabang Selatan',
                'code' => 'OUT003',
                'address' => 'Jl. Fatmawati No. 789, Jakarta Selatan',
                'phone' => '021-11223344',
                'email' => 'selatan@kasirpos.com',
                'is_active' => true,
            ],
        ];

        foreach ($outlets as $outlet) {
            Outlet::create($outlet);
        }
    }
}
