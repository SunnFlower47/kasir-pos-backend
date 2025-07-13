<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Outlet;
use App\Models\User;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get required data
        $products = Product::take(3)->get();
        $customer = Customer::first();
        $outlet = Outlet::first();
        $user = User::first();

        if ($products->isEmpty() || !$customer || !$outlet || !$user) {
            $this->command->warn('Missing required data. Please ensure you have products, customers, outlets, and users.');
            return;
        }

        // Create test transactions
        $transactions = [
            [
                'transaction_date' => now()->subDays(5),
                'items' => [
                    ['product_id' => $products[0]->id, 'quantity' => 2, 'unit_price' => 15000],
                    ['product_id' => $products[1]->id, 'quantity' => 1, 'unit_price' => 25000],
                ]
            ],
            [
                'transaction_date' => now()->subDays(3),
                'items' => [
                    ['product_id' => $products[1]->id, 'quantity' => 3, 'unit_price' => 25000],
                ]
            ],
            [
                'transaction_date' => now()->subDays(1),
                'items' => [
                    ['product_id' => $products[0]->id, 'quantity' => 1, 'unit_price' => 15000],
                    ['product_id' => $products[2]->id, 'quantity' => 2, 'unit_price' => 35000],
                ]
            ],
        ];

        foreach ($transactions as $index => $transactionData) {
            // Calculate totals
            $subtotal = 0;
            foreach ($transactionData['items'] as $item) {
                $subtotal += $item['quantity'] * $item['unit_price'];
            }

            $tax = $subtotal * 0.1; // 10% tax
            $total = $subtotal + $tax;

            // Create transaction
            $transaction = Transaction::create([
                'transaction_number' => 'TRX' . date('YmdHis') . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                'customer_id' => $customer->id,
                'outlet_id' => $outlet->id,
                'user_id' => $user->id,
                'transaction_date' => $transactionData['transaction_date'],
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'discount_amount' => 0,
                'total_amount' => $total,
                'paid_amount' => $total,
                'change_amount' => 0,
                'payment_method' => 'cash',
                'status' => 'completed',
                'notes' => 'Test transaction ' . ($index + 1),
            ]);

            // Create transaction items
            foreach ($transactionData['items'] as $itemData) {
                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $itemData['product_id'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total_price' => $itemData['quantity'] * $itemData['unit_price'],
                ]);
            }
        }

        $this->command->info('Created ' . count($transactions) . ' test transactions');
    }
}
