<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run seeders
        $this->artisan('db:seed');
    }

    public function test_can_get_dashboard_data(): void
    {
        $user = User::first();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'stats' => [
                            'total_outlets',
                            'total_products',
                            'total_customers',
                            'total_suppliers',
                            'total_users'
                        ],
                        'transaction_stats' => [
                            'transactions_today',
                            'revenue_today',
                            'transactions_this_month',
                            'revenue_this_month'
                        ],
                        'stock_stats' => [
                            'low_stock_products',
                            'out_of_stock_products',
                            'total_stock_value'
                        ],
                        'recent_transactions',
                        'top_products',
                        'low_stock_products',
                        'sales_chart_data'
                    ]
                ]);
    }

    public function test_can_get_outlet_comparison(): void
    {
        $user = User::role('Admin')->first();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard/outlet-comparison');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'period',
                        'outlets',
                        'total_revenue',
                        'total_transactions'
                    ]
                ]);
    }

    public function test_unauthorized_user_cannot_access_outlet_comparison(): void
    {
        $user = User::role('Cashier')->first();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard/outlet-comparison');

        $response->assertStatus(403);
    }

    public function test_can_get_outlet_dashboard(): void
    {
        $user = User::role('Admin')->first();
        Sanctum::actingAs($user);

        $outlet = \App\Models\Outlet::first();

        $response = $this->getJson("/api/v1/outlets/{$outlet->id}/dashboard");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'outlet',
                        'stats',
                        'recent_transactions',
                        'low_stock_products'
                    ]
                ]);
    }
}
