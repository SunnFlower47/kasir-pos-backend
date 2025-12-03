<?php

namespace App\Exports;

use App\Http\Controllers\Api\EnhancedReportController;
use Illuminate\Support\Facades\App;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\BaseReportSheet;

class EnhancedReportExport implements WithMultipleSheets
{
    protected $params;

    public function __construct(array $params = [])
    {
        $this->params = $params;
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        $sheets = [];

        // Get enhanced report data
        $controller = App::make(EnhancedReportController::class);
        $request = new \Illuminate\Http\Request($this->params);
        $response = $controller->index($request);
        $data = json_decode($response->getContent(), true);

        if (!$data['success']) {
            return $sheets;
        }

        $reportData = $data['data'] ?? [];

        // Sheet 1: Summary
        $sheets[] = new EnhancedReportSummarySheet($reportData);

        // Sheet 2: Top Products
        $sheets[] = new EnhancedReportTopProductsSheet($reportData);

        // Sheet 3: Payment Methods
        $sheets[] = new EnhancedReportPaymentMethodsSheet($reportData);

        return $sheets;
    }
}

class EnhancedReportSummarySheet extends BaseReportSheet
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        $summary = $this->data['summary'] ?? [];
        
        return collect([
            [
                'Total Pendapatan' => number_format($summary['total_revenue'] ?? 0, 2, '.', ''),
                'Total Transaksi' => $summary['total_transactions'] ?? 0,
                'Rata-rata Transaksi' => number_format($summary['avg_transaction_value'] ?? 0, 2, '.', ''),
                'Total Pelanggan' => $summary['total_customers'] ?? 0,
                'Total Produk' => $summary['total_products'] ?? 0,
            ]
        ]);
    }

    public function headings(): array
    {
        return ['Total Pendapatan', 'Total Transaksi', 'Rata-rata Transaksi', 'Total Pelanggan', 'Total Produk'];
    }

    public function title(): string
    {
        return 'Ringkasan';
    }
}

class EnhancedReportTopProductsSheet extends BaseReportSheet
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        $topProducts = $this->data['top_products'] ?? [];
        
        return collect($topProducts)->map(function ($product) {
            return [
                'Nama Produk' => $product['name'] ?? '',
                'SKU' => $product['sku'] ?? '',
                'Kategori' => $product['category_name'] ?? '',
                'Terjual' => $product['total_sold'] ?? 0,
                'Total Pendapatan' => number_format($product['total_revenue'] ?? 0, 2, '.', ''),
            ];
        });
    }

    public function headings(): array
    {
        return ['Nama Produk', 'SKU', 'Kategori', 'Terjual', 'Total Pendapatan'];
    }

    public function title(): string
    {
        return 'Produk Terlaris';
    }
}

class EnhancedReportPaymentMethodsSheet extends BaseReportSheet
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        $paymentMethods = $this->data['payment_methods'] ?? [];
        
        return collect($paymentMethods)->map(function ($method) {
            return [
                'Metode Pembayaran' => $method['method'] ?? '',
                'Jumlah Transaksi' => $method['count'] ?? 0,
                'Total Pendapatan' => number_format($method['amount'] ?? 0, 2, '.', ''),
                'Persentase' => number_format($method['percentage'] ?? 0, 2, '.', '') . '%',
            ];
        });
    }

    public function headings(): array
    {
        return ['Metode Pembayaran', 'Jumlah Transaksi', 'Total Pendapatan', 'Persentase'];
    }

    public function title(): string
    {
        return 'Metode Pembayaran';
    }
}

