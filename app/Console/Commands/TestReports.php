<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Http\Request;

class TestReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:reports';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test report endpoints';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Report Endpoints...');

        // Create a mock user for testing
        $user = \App\Models\User::first();
        if (!$user) {
            $this->error('No user found. Please create a user first.');
            return;
        }

        // Login the user
        auth()->login($user);

        $controller = new ReportController();

        // Test expenses endpoint
        $this->info('=== TESTING EXPENSES ENDPOINT ===');
        $request = new Request();

        try {
            $response = $controller->expenses($request);
            $data = $response->getData(true);
            $this->info('Expenses Response: ' . json_encode($data, JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            $this->error('Expenses Error: ' . $e->getMessage());
        }

        // Test profit endpoint
        $this->info('=== TESTING PROFIT ENDPOINT ===');
        try {
            $response = $controller->profit($request);
            $data = $response->getData(true);
            $this->info('Profit Response: ' . json_encode($data, JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            $this->error('Profit Error: ' . $e->getMessage());
        }

        // Test sales endpoint
        $this->info('=== TESTING SALES ENDPOINT ===');
        try {
            $response = $controller->sales($request);
            $data = $response->getData(true);
            $this->info('Sales Response: ' . json_encode($data, JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            $this->error('Sales Error: ' . $e->getMessage());
        }

        // Test purchases endpoint
        $this->info('=== TESTING PURCHASES ENDPOINT ===');
        try {
            $response = $controller->purchases($request);
            $data = $response->getData(true);
            $this->info('Purchases Response: ' . json_encode($data, JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            $this->error('Purchases Error: ' . $e->getMessage());
        }

        // Test transaction detail endpoint
        $this->info('=== TESTING TRANSACTION DETAIL ENDPOINT ===');
        try {
            $transactionController = new \App\Http\Controllers\Api\TransactionController();
            $transaction = \App\Models\Transaction::first();
            if ($transaction) {
                $response = $transactionController->show($transaction);
                $data = $response->getData(true);
                $this->info('Transaction Detail Response: ' . json_encode($data, JSON_PRETTY_PRINT));
            } else {
                $this->warn('No transactions found');
            }
        } catch (\Exception $e) {
            $this->error('Transaction Detail Error: ' . $e->getMessage());
        }

        // Test PDF receipt generation
        $this->info('=== TESTING PDF RECEIPT GENERATION ===');
        try {
            $receiptController = new \App\Http\Controllers\Api\ReceiptController();
            $transaction = \App\Models\Transaction::find(17);
            if ($transaction) {
                $this->info('Testing PDF for transaction: ' . $transaction->transaction_number);
                $response = $receiptController->generatePdf($transaction);
                $this->info('PDF generated successfully! Size: ' . strlen($response->getContent()) . ' bytes');
            } else {
                $this->warn('Transaction 17 not found');
            }
        } catch (\Exception $e) {
            $this->error('PDF Generation Error: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
        }
    }
}
