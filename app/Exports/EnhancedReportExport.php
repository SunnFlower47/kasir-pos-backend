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
                'total_pendapatan' => (float)($summary['total_revenue'] ?? 0),
                'total_transaksi' => (int)($summary['total_transactions'] ?? 0),
                'rata_transaksi' => (float)($summary['avg_transaction_value'] ?? 0),
                'total_pelanggan' => (int)($summary['total_customers'] ?? 0),
                'total_produk' => (int)($summary['total_products'] ?? 0),
            ]
        ]);
    }

    public function map($row): array
    {
        return [
            number_format($row['total_pendapatan'] ?? 0, 2, '.', ''),
            (int)($row['total_transaksi'] ?? 0),
            number_format($row['rata_transaksi'] ?? 0, 2, '.', ''),
            (int)($row['total_pelanggan'] ?? 0),
            (int)($row['total_produk'] ?? 0),
        ];
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

        return collect($topProducts);
    }

    public function map($product): array
    {
        return [
            $product['name'] ?? '',
            $product['sku'] ?? '',
            $product['category_name'] ?? '',
            (int)($product['total_sold'] ?? 0),
            number_format((float)($product['total_revenue'] ?? 0), 2, '.', ''),
        ];
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

        return collect($paymentMethods);
    }

    public function map($method): array
    {
        return [
            $method['method'] ?? '',
            (int)($method['count'] ?? 0),
            number_format((float)($method['amount'] ?? 0), 2, '.', ''),
            number_format((float)($method['percentage'] ?? 0), 2, '.', '') . '%',
        ];
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

