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

        $revenue = $kpis['revenue']['net_revenue'] ?? $kpis['revenue']['total'] ?? 0;
        $transactions = $kpis['transactions']['current'] ?? 0;
        $avgValue = $kpis['avg_transaction_value']['current'] ?? 0;
        $customers = $kpis['customers']['active'] ?? 0;
        $growthRate = $kpis['revenue']['growth_rate'] ?? 0;

        return collect([
            [
                'total_revenue' => (float)$revenue,
                'total_transactions' => (int)$transactions,
                'avg_transaction_value' => (float)$avgValue,
                'total_customers' => (int)$customers,
                'growth_rate' => (float)$growthRate,
            ]
        ]);
    }

    public function map($row): array
    {
        return [
            number_format($row['total_revenue'] ?? 0, 2, '.', ''),
            (int)($row['total_transactions'] ?? 0),
            number_format($row['avg_transaction_value'] ?? 0, 2, '.', ''),
            (int)($row['total_customers'] ?? 0),
            number_format($row['growth_rate'] ?? 0, 2, '.', '') . '%',
        ];
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

        return collect($byPaymentMethod);
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
            number_format((float)($product['total_profit'] ?? 0), 2, '.', ''),
            number_format((float)($product['profit_margin'] ?? 0), 2, '.', '') . '%',
        ];
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

