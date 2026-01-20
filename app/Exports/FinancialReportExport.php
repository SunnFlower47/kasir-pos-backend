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

class FinancialReportSummarySheet extends BaseReportSheet implements \Maatwebsite\Excel\Concerns\WithStyles, \Maatwebsite\Excel\Concerns\ShouldAutoSize, \Maatwebsite\Excel\Concerns\WithColumnFormatting
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
                'periode' => ($this->data['period']['from'] ?? '') . ' s/d ' . ($this->data['period']['to'] ?? ''),
                'total_pendapatan' => (float)($summary['total_revenue'] ?? 0),
                'total_refund' => (float)($summary['total_refunds'] ?? 0),
                'pendapatan_bersih' => (float)($summary['net_revenue'] ?? 0),
                'total_pengeluaran' => (float)($summary['total_expenses'] ?? 0),
                'pengeluaran_pembelian' => (float)($summary['purchase_expenses'] ?? 0),
                'pengeluaran_operasional' => (float)($summary['operational_expenses'] ?? 0),
                'total_hpp' => (float)($summary['total_cogs'] ?? 0),
                'laba_kotor' => (float)($summary['gross_profit'] ?? 0),
                'laba_bersih' => (float)($summary['net_profit'] ?? 0),
            ]
        ]);
    }

    public function map($row): array
    {
        return [
            $row['periode'] ?? '',
            $row['total_pendapatan'] ?? 0,
            $row['total_refund'] ?? 0,
            $row['pendapatan_bersih'] ?? 0,
            $row['total_pengeluaran'] ?? 0,
            $row['pengeluaran_pembelian'] ?? 0,
            $row['pengeluaran_operasional'] ?? 0,
            $row['total_hpp'] ?? 0,
            $row['laba_kotor'] ?? 0,
            $row['laba_bersih'] ?? 0,
        ];
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

    public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E3F2FD']
                ],
            ],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => '#,##0.00',
            'C' => '#,##0.00',
            'D' => '#,##0.00',
            'E' => '#,##0.00',
            'F' => '#,##0.00',
            'G' => '#,##0.00',
            'H' => '#,##0.00',
            'I' => '#,##0.00',
            'J' => '#,##0.00',
        ];
    }

    public function title(): string
    {
        return 'Ringkasan';
    }
}

class FinancialReportRevenueSheet extends BaseReportSheet implements \Maatwebsite\Excel\Concerns\WithStyles, \Maatwebsite\Excel\Concerns\ShouldAutoSize, \Maatwebsite\Excel\Concerns\WithColumnFormatting
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
                'total_pendapatan' => (float)($revenue['total'] ?? 0),
                'total_transaksi' => (int)($revenue['transaction_count'] ?? 0),
                'rata_transaksi' => (float)($revenue['avg_transaction_value'] ?? 0),
                'total_refund' => (float)($revenue['refunds'] ?? 0),
                'pendapatan_bersih' => (float)($revenue['net_revenue'] ?? 0),
            ]
        ]);
    }

    public function map($row): array
    {
        return [
            $row['total_pendapatan'] ?? 0,
            (int)($row['total_transaksi'] ?? 0),
            $row['rata_transaksi'] ?? 0,
            $row['total_refund'] ?? 0,
            $row['pendapatan_bersih'] ?? 0,
        ];
    }

    public function headings(): array
    {
        return ['Total Pendapatan', 'Total Transaksi', 'Rata-rata Transaksi', 'Total Refund', 'Pendapatan Bersih'];
    }

    public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E3F2FD']
                ],
            ],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => '#,##0.00',
            'C' => '#,##0.00',
            'D' => '#,##0.00',
            'E' => '#,##0.00',
        ];
    }

    public function title(): string
    {
        return 'Pendapatan';
    }
}

class FinancialReportExpensesSheet extends BaseReportSheet implements \Maatwebsite\Excel\Concerns\WithStyles, \Maatwebsite\Excel\Concerns\ShouldAutoSize, \Maatwebsite\Excel\Concerns\WithColumnFormatting
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
                'total_pengeluaran' => (float)($expenses['total'] ?? 0),
                'pengeluaran_pembelian' => (float)($expenses['purchase_expenses'] ?? 0),
                'pengeluaran_operasional' => (float)($expenses['operational_expenses'] ?? 0),
                'jumlah_pembelian' => (int)($expenses['purchase_count'] ?? 0),
            ]
        ]);
    }

    public function map($row): array
    {
        return [
            $row['total_pengeluaran'] ?? 0,
            $row['pengeluaran_pembelian'] ?? 0,
            $row['pengeluaran_operasional'] ?? 0,
            (int)($row['jumlah_pembelian'] ?? 0),
        ];
    }

    public function headings(): array
    {
        return ['Total Pengeluaran', 'Pengeluaran Pembelian', 'Pengeluaran Operasional', 'Jumlah Pembelian'];
    }

    public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E3F2FD']
                ],
            ],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => '#,##0.00',
            'B' => '#,##0.00',
            'C' => '#,##0.00',
        ];
    }

    public function title(): string
    {
        return 'Pengeluaran';
    }
}

class FinancialReportProfitLossSheet extends BaseReportSheet implements \Maatwebsite\Excel\Concerns\WithStyles, \Maatwebsite\Excel\Concerns\ShouldAutoSize, \Maatwebsite\Excel\Concerns\WithColumnFormatting
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
                'laba_kotor' => (float)($profitLoss['gross_profit'] ?? 0),
                'laba_bersih' => (float)($profitLoss['net_profit'] ?? 0),
                'pengeluaran_operasional' => (float)($profitLoss['operating_expenses'] ?? 0),
                'margin_laba_kotor' => (float)($profitLoss['gross_profit_margin'] ?? 0),
                'margin_laba_bersih' => (float)($profitLoss['net_profit_margin'] ?? 0),
                'menguntungkan' => ($profitLoss['is_profitable'] ?? false) ? 'Ya' : 'Tidak',
            ]
        ]);
    }

    public function map($row): array
    {
        return [
            $row['laba_kotor'] ?? 0,
            $row['laba_bersih'] ?? 0,
            $row['pengeluaran_operasional'] ?? 0,
            ($row['margin_laba_kotor'] ?? 0) / 100, // Excel expects decimal for percentage
            ($row['margin_laba_bersih'] ?? 0) / 100,
            $row['menguntungkan'] ?? 'Tidak',
        ];
    }

    public function headings(): array
    {
        return ['Laba Kotor', 'Laba Bersih', 'Pengeluaran Operasional', 'Margin Laba Kotor', 'Margin Laba Bersih', 'Menguntungkan'];
    }

    public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E3F2FD']
                ],
            ],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => '#,##0.00',
            'B' => '#,##0.00',
            'C' => '#,##0.00',
            'D' => '0.00%',
            'E' => '0.00%',
        ];
    }

    public function title(): string
    {
        return 'Laba Rugi';
    }
}

