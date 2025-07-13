<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run seeders
        $this->artisan('db:seed');
    }

    public function test_can_create_transaction(): void
    {
        $user = User::role('Cashier')->first();
        Sanctum::actingAs($user);

        $outlet = Outlet::first();
        $product = Product::first();
        $customer = Customer::first();

        // Ensure product has stock
        $productStock = ProductStock::where('product_id', $product->id)
                                   ->where('outlet_id', $outlet->id)
                                   ->first();
        $productStock->update(['quantity' => 100]);

        $totalAmount = $product->selling_price * 2; // 2 items
        $transactionData = [
            'outlet_id' => $outlet->id,
            'customer_id' => $customer->id,
            'paid_amount' => $totalAmount + 5000, // Add extra to ensure sufficient payment
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => $product->selling_price,
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/transactions', $transactionData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'transaction_number',
                        'total_amount',
                        'paid_amount',
                        'change_amount',
                        'status'
                    ]
                ]);

        $this->assertDatabaseHas('transactions', [
            'outlet_id' => $outlet->id,
            'customer_id' => $customer->id,
            'status' => 'completed'
        ]);

        // Check stock was reduced
        $updatedStock = ProductStock::where('product_id', $product->id)
                                   ->where('outlet_id', $outlet->id)
                                   ->first();
        $this->assertEquals(98, $updatedStock->quantity);
    }

    public function test_cannot_create_transaction_with_insufficient_stock(): void
    {
        $user = User::role('Cashier')->first();
        Sanctum::actingAs($user);

        $outlet = Outlet::first();
        $product = Product::first();

        // Set low stock
        $productStock = ProductStock::where('product_id', $product->id)
                                   ->where('outlet_id', $outlet->id)
                                   ->first();
        $productStock->update(['quantity' => 1]);

        $totalAmount = $product->selling_price * 5; // 5 items
        $transactionData = [
            'outlet_id' => $outlet->id,
            'paid_amount' => $totalAmount + 5000, // Sufficient payment
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5, // More than available stock
                    'unit_price' => $product->selling_price,
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/transactions', $transactionData);

        $response->assertStatus(500)
                ->assertJsonStructure([
                    'success',
                    'message'
                ]);
    }

    public function test_can_get_transactions_list(): void
    {
        $user = User::first();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/transactions');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'data' => []
                    ]
                ]);
    }

    public function test_can_refund_transaction(): void
    {
        $user = User::role('Admin')->first();
        Sanctum::actingAs($user);

        // Create a transaction first
        $transaction = Transaction::factory()->create([
            'status' => 'completed'
        ]);

        $response = $this->postJson("/api/v1/transactions/{$transaction->id}/refund", [
            'reason' => 'Customer request'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message'
                ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'refunded'
        ]);
    }

    public function test_unauthorized_user_cannot_refund(): void
    {
        $user = User::role('Cashier')->first();
        Sanctum::actingAs($user);

        $transaction = Transaction::factory()->create([
            'status' => 'completed'
        ]);

        $response = $this->postJson("/api/v1/transactions/{$transaction->id}/refund", [
            'reason' => 'Customer request'
        ]);

        $response->assertStatus(403);
    }
}
