<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run seeders
        $this->artisan('db:seed');
    }

    public function test_can_get_products_list(): void
    {
        // Create authenticated user
        $user = User::first();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'name',
                                'sku',
                                'barcode',
                                'category',
                                'unit',
                                'selling_price',
                                'is_active'
                            ]
                        ]
                    ]
                ]);
    }

    public function test_can_create_product(): void
    {
        $user = User::role('Super Admin')->first();
        Sanctum::actingAs($user);

        $category = Category::first();
        $unit = Unit::first();

        $productData = [
            'name' => 'Test Product',
            'sku' => 'TEST001',
            'barcode' => '1234567890123',
            'description' => 'Test product description',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 10000,
            'selling_price' => 15000,
            'wholesale_price' => 13000,
            'min_stock' => 5,
            'is_active' => true,
        ];

        $response = $this->postJson('/api/v1/products', $productData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'name',
                        'sku',
                        'category',
                        'unit'
                    ]
                ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Test Product',
            'sku' => 'TEST001'
        ]);
    }

    public function test_can_get_product_by_barcode(): void
    {
        $user = User::first();
        Sanctum::actingAs($user);

        $product = Product::first();
        $outlet = Outlet::first();

        $response = $this->getJson('/api/v1/products/barcode/scan?' . http_build_query([
            'barcode' => $product->barcode,
            'outlet_id' => $outlet->id
        ]));

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'name',
                        'barcode',
                        'stock_quantity',
                        'is_low_stock'
                    ]
                ]);
    }

    public function test_unauthorized_user_cannot_create_product(): void
    {
        $user = User::role('Cashier')->first();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Test Product'
        ]);

        $response->assertStatus(403);
    }

    public function test_can_search_products(): void
    {
        $user = User::first();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/products?search=Nasi');

        $response->assertStatus(200);
    }
}
