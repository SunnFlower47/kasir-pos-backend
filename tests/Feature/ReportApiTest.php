<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run seeders
        $this->artisan('db:seed');
    }

    public function test_can_get_sales_report(): void
    {
        $user = User::role('Admin')->first();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/reports/sales?' . http_build_query([
            'date_from' => '2025-01-01',
            'date_to' => '2025-12-31',
        ]));

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'transactions',
                        'summary' => [
                            'total_transactions',
                            'total_revenue',
                            'total_discount',
                            'total_tax',
                            'avg_transaction_value'
                        ]
                    ]
                ]);
    }

    public function test_can_get_purchases_report(): void
    {
        $user = User::role('Admin')->first();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/reports/purchases?' . http_build_query([
            'date_from' => '2025-01-01',
            'date_to' => '2025-12-31',
        ]));

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'purchases',
                        'summary' => [
                            'total_purchases',
                            'total_amount',
                            'total_paid',
                            'total_remaining'
                        ]
                    ]
                ]);
    }

    public function test_can_get_stocks_report(): void
    {
        $user = User::role('Admin')->first();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/reports/stocks');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'stocks',
                        'summary' => [
                            'total_products',
                            'total_stock_value',
                            'low_stock_products',
                            'out_of_stock_products'
                        ]
                    ]
                ]);
    }

    public function test_can_get_profit_report(): void
    {
        $user = User::role('Admin')->first();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/reports/profit?' . http_build_query([
            'date_from' => '2025-01-01',
            'date_to' => '2025-12-31',
        ]));

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'total_revenue',
                        'total_cost',
                        'total_profit',
                        'profit_margin'
                    ]
                ]);
    }

    public function test_can_get_top_products_report(): void
    {
        $user = User::role('Admin')->first();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/reports/top-products?' . http_build_query([
            'date_from' => '2025-01-01',
            'date_to' => '2025-12-31',
            'limit' => 5,
        ]));

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'sku',
                            'category_name',
                            'total_sold',
                            'total_revenue'
                        ]
                    ]
                ]);
    }

    public function test_unauthorized_user_cannot_access_reports(): void
    {
        $user = User::role('Cashier')->first();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/reports/sales?' . http_build_query([
            'date_from' => '2025-01-01',
            'date_to' => '2025-12-31',
        ]));

        $response->assertStatus(403);
    }
}
