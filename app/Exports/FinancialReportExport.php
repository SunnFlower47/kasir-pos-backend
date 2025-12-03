<?php

namespace App\Exports;

use App\Http\Controllers\Api\FinancialReportController;
use Illuminate\Support\Facades\App;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\BaseReportSheet;

class FinancialReportExport implements WithMultipleSheets
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

        // Get financial report data
        $controller = App::make(FinancialReportController::class);
        $request = new \Illuminate\Http\Request($this->params);
        $response = $controller->comprehensive($request);
        $data = json_decode($response->getContent(), true);

        if (!$data['success']) {
            return $sheets;
        }

        $reportData = $data['data'] ?? [];

        // Sheet 1: Summary
        $sheets[] = new FinancialReportSummarySheet($reportData);

        // Sheet 2: Revenue
        $sheets[] = new FinancialReportRevenueSheet($reportData);

        // Sheet 3: Expenses
        $sheets[] = new FinancialReportExpensesSheet($reportData);

        // Sheet 4: Profit Loss
        $sheets[] = new FinancialReportProfitLossSheet($reportData);

        return $sheets;
    }
}

class FinancialReportSummarySheet extends BaseReportSheet
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
                'Periode' => ($this->data['period']['from'] ?? '') . ' s/d ' . ($this->data['period']['to'] ?? ''),
                'Total Pendapatan' => number_format($summary['total_revenue'] ?? 0, 2, '.', ''),
                'Total Refund' => number_format($summary['total_refunds'] ?? 0, 2, '.', ''),
                'Pendapatan Bersih' => number_format($summary['net_revenue'] ?? 0, 2, '.', ''),
                'Total Pengeluaran' => number_format($summary['total_expenses'] ?? 0, 2, '.', ''),
                'Pengeluaran Pembelian' => number_format($summary['purchase_expenses'] ?? 0, 2, '.', ''),
                'Pengeluaran Operasional' => number_format($summary['operational_expenses'] ?? 0, 2, '.', ''),
                'Total HPP' => number_format($summary['total_cogs'] ?? 0, 2, '.', ''),
                'Laba Kotor' => number_format($summary['gross_profit'] ?? 0, 2, '.', ''),
                'Laba Bersih' => number_format($summary['net_profit'] ?? 0, 2, '.', ''),
            ]
        ]);
    }

    public function headings(): array
    {
        return [
            'Periode',
            'Total Pendapatan',
            'Total Refund',
            'Pendapatan Bersih',
            'Total Pengeluaran',
            'Pengeluaran Pembelian',
            'Pengeluaran Operasional',
            'Total HPP',
            'Laba Kotor',
            'Laba Bersih',
        ];
    }

    public function title(): string
    {
        return 'Ringkasan';
    }
}

class FinancialReportRevenueSheet extends BaseReportSheet
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        $revenue = $this->data['revenue'] ?? [];
        
        return collect([
            [
                'Total Pendapatan' => number_format($revenue['total'] ?? 0, 2, '.', ''),
                'Total Transaksi' => $revenue['transaction_count'] ?? 0,
                'Rata-rata Transaksi' => number_format($revenue['avg_transaction_value'] ?? 0, 2, '.', ''),
                'Total Refund' => number_format($revenue['refunds'] ?? 0, 2, '.', ''),
                'Pendapatan Bersih' => number_format($revenue['net_revenue'] ?? 0, 2, '.', ''),
            ]
        ]);
    }

    public function headings(): array
    {
        return ['Total Pendapatan', 'Total Transaksi', 'Rata-rata Transaksi', 'Total Refund', 'Pendapatan Bersih'];
    }

    public function title(): string
    {
        return 'Pendapatan';
    }
}

class FinancialReportExpensesSheet extends BaseReportSheet
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        $expenses = $this->data['expenses'] ?? [];
        
        return collect([
            [
                'Total Pengeluaran' => number_format($expenses['total'] ?? 0, 2, '.', ''),
                'Pengeluaran Pembelian' => number_format($expenses['purchase_expenses'] ?? 0, 2, '.', ''),
                'Pengeluaran Operasional' => number_format($expenses['operational_expenses'] ?? 0, 2, '.', ''),
                'Jumlah Pembelian' => $expenses['purchase_count'] ?? 0,
            ]
        ]);
    }

    public function headings(): array
    {
        return ['Total Pengeluaran', 'Pengeluaran Pembelian', 'Pengeluaran Operasional', 'Jumlah Pembelian'];
    }

    public function title(): string
    {
        return 'Pengeluaran';
    }
}

class FinancialReportProfitLossSheet extends BaseReportSheet
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        $profitLoss = $this->data['profit_loss'] ?? [];
        
        return collect([
            [
                'Laba Kotor' => number_format($profitLoss['gross_profit'] ?? 0, 2, '.', ''),
                'Laba Bersih' => number_format($profitLoss['net_profit'] ?? 0, 2, '.', ''),
                'Pengeluaran Operasional' => number_format($profitLoss['operating_expenses'] ?? 0, 2, '.', ''),
                'Margin Laba Kotor' => number_format($profitLoss['gross_profit_margin'] ?? 0, 2, '.', '') . '%',
                'Margin Laba Bersih' => number_format($profitLoss['net_profit_margin'] ?? 0, 2, '.', '') . '%',
                'Menguntungkan' => ($profitLoss['is_profitable'] ?? false) ? 'Ya' : 'Tidak',
            ]
        ]);
    }

    public function headings(): array
    {
        return ['Laba Kotor', 'Laba Bersih', 'Pengeluaran Operasional', 'Margin Laba Kotor (%)', 'Margin Laba Bersih (%)', 'Menguntungkan'];
    }

    public function title(): string
    {
        return 'Laba Rugi';
    }
}

