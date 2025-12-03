<?php

namespace App\Exports;

use App\Http\Controllers\Api\AdvancedReportController;
use Illuminate\Support\Facades\App;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\BaseReportSheet;

class AdvancedReportExport implements WithMultipleSheets
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

        // Get advanced report data
        $controller = App::make(AdvancedReportController::class);
        $request = new \Illuminate\Http\Request($this->params);
        $response = $controller->businessIntelligence($request);
        $data = json_decode($response->getContent(), true);

        if (!$data['success']) {
            return $sheets;
        }

        $reportData = $data['data'] ?? [];

        // Sheet 1: KPIs
        $sheets[] = new AdvancedReportKPIsSheet($reportData);

        // Sheet 2: Revenue Analytics
        $sheets[] = new AdvancedReportRevenueAnalyticsSheet($reportData);

        // Sheet 3: Product Performance
        $sheets[] = new AdvancedReportProductPerformanceSheet($reportData);

        return $sheets;
    }
}

class AdvancedReportKPIsSheet extends BaseReportSheet
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        $kpis = $this->data['kpis'] ?? [];
        
        return collect([
            [
                'Total Revenue' => number_format($kpis['total_revenue'] ?? 0, 2, '.', ''),
                'Total Transactions' => $kpis['total_transactions'] ?? 0,
                'Average Transaction Value' => number_format($kpis['avg_transaction_value'] ?? 0, 2, '.', ''),
                'Total Customers' => $kpis['total_customers'] ?? 0,
                'Growth Rate' => number_format($kpis['growth_rate'] ?? 0, 2, '.', '') . '%',
            ]
        ]);
    }

    public function headings(): array
    {
        return ['Total Revenue', 'Total Transactions', 'Average Transaction Value', 'Total Customers', 'Growth Rate (%)'];
    }

    public function title(): string
    {
        return 'KPIs';
    }
}

class AdvancedReportRevenueAnalyticsSheet extends BaseReportSheet
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        $revenueAnalytics = $this->data['revenue_analytics'] ?? [];
        $byPaymentMethod = $revenueAnalytics['by_payment_method'] ?? [];
        
        return collect($byPaymentMethod)->map(function ($method) {
            return [
                'Payment Method' => $method['method'] ?? '',
                'Count' => $method['count'] ?? 0,
                'Amount' => number_format($method['amount'] ?? 0, 2, '.', ''),
                'Percentage' => number_format($method['percentage'] ?? 0, 2, '.', '') . '%',
            ];
        });
    }

    public function headings(): array
    {
        return ['Payment Method', 'Count', 'Amount', 'Percentage'];
    }

    public function title(): string
    {
        return 'Revenue Analytics';
    }
}

class AdvancedReportProductPerformanceSheet extends BaseReportSheet
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        $productAnalytics = $this->data['product_analytics'] ?? [];
        $topProducts = $productAnalytics['top_products'] ?? [];
        
        return collect($topProducts)->map(function ($product) {
            return [
                'Product Name' => $product['name'] ?? '',
                'SKU' => $product['sku'] ?? '',
                'Category' => $product['category_name'] ?? '',
                'Total Sold' => $product['total_sold'] ?? 0,
                'Total Revenue' => number_format($product['total_revenue'] ?? 0, 2, '.', ''),
                'Total Profit' => number_format($product['total_profit'] ?? 0, 2, '.', ''),
                'Profit Margin' => number_format($product['profit_margin'] ?? 0, 2, '.', '') . '%',
            ];
        });
    }

    public function headings(): array
    {
        return ['Product Name', 'SKU', 'Category', 'Total Sold', 'Total Revenue', 'Total Profit', 'Profit Margin (%)'];
    }

    public function title(): string
    {
        return 'Product Performance';
    }
}

