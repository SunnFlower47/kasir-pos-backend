<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run()
    {
        $plans = [
            [
                'name' => 'Paket Bulanan',
                'slug' => 'monthly',
                'price' => 100000.00,
                'duration_in_days' => 30,
                'features' => ['web', 'mobile', 'desktop'],
                'description' => 'Bayar bulanan, fleksibel.',
                'is_active' => true,
            ],
            [
                'name' => 'Paket Tahunan',
                'slug' => 'yearly',
                'price' => 1000000.00,
                'duration_in_days' => 365,
                'features' => ['web', 'mobile', 'desktop'],
                'description' => 'Hemat biaya dengan bayar tahunan.',
                'is_active' => true,
            ]
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
