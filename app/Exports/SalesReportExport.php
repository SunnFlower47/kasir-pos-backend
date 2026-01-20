<?php

namespace App\Exports;

use App\Models\Transaction;
use App\Models\TransactionItem;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use App\Exports\BaseReportSheet;

class SalesReportExport implements WithMultipleSheets
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

        // Sheet 1: Summary
        $sheets[] = new SalesReportSummarySheet($this->params);

        // Sheet 2: Transactions
        $sheets[] = new SalesReportTransactionsSheet($this->params);

        // Sheet 3: Top Products
        $sheets[] = new SalesReportTopProductsSheet($this->params);

        return $sheets;
    }
}

class SalesReportSummarySheet extends BaseReportSheet implements \Maatwebsite\Excel\Concerns\WithStyles, \Maatwebsite\Excel\Concerns\ShouldAutoSize, \Maatwebsite\Excel\Concerns\WithColumnFormatting
{
    public function collection()
    {
        $dateFrom = $this->params['date_from'] ?? now()->subDays(30)->format('Y-m-d');
        $dateTo = $this->params['date_to'] ?? now()->format('Y-m-d');

        $query = Transaction::where('status', 'completed');

        if (isset($this->params['date_from']) && isset($this->params['date_to'])) {
            $query->whereBetween('transaction_date', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ]);
        }

        if (isset($this->params['outlet_id'])) {
            $query->where('outlet_id', $this->params['outlet_id']);
        }

        if (isset($this->params['tenant_id'])) {
            $query->where('tenant_id', $this->params['tenant_id']);
        }

        $summary = [
            [

                'Periode' => $dateFrom . ' s/d ' . $dateTo,
                'Total Transaksi' => $query->count(),
                'Total Pendapatan' => (float)$query->sum('total_amount'),
                'Total Diskon' => (float)$query->sum('discount_amount'),
                'Total Pajak' => (float)$query->sum('tax_amount'),
                'Rata-rata Transaksi' => (float)$query->avg('total_amount'),
            ]
        ];

        return collect($summary);
    }

    public function map($row): array
    {
        return [
            $row['Periode'] ?? '',
            $row['Total Transaksi'] ?? 0,
            $row['Total Pendapatan'] ?? 0,
            $row['Total Diskon'] ?? 0,
            $row['Total Pajak'] ?? 0,
            $row['Rata-rata Transaksi'] ?? 0,
        ];
    }

    public function headings(): array
    {
        return ['Periode', 'Total Transaksi', 'Total Pendapatan', 'Total Diskon', 'Total Pajak', 'Rata-rata Transaksi'];
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
            'C' => '#,##0.00', // Total Pendapatan
            'D' => '#,##0.00', // Total Diskon
            'E' => '#,##0.00', // Total Pajak
            'F' => '#,##0.00', // Rata-rata Transaksi
        ];
    }

    public function title(): string
    {
        return 'Ringkasan';
    }
}

class SalesReportTransactionsSheet extends BaseReportSheet implements \Maatwebsite\Excel\Concerns\WithStyles, \Maatwebsite\Excel\Concerns\ShouldAutoSize, \Maatwebsite\Excel\Concerns\WithColumnFormatting
{
    public function collection()
    {
        $dateFrom = $this->params['date_from'] ?? now()->subDays(30)->format('Y-m-d');
        $dateTo = $this->params['date_to'] ?? now()->format('Y-m-d');

        $query = Transaction::with(['outlet', 'user', 'customer'])
            ->where('status', 'completed');

        if (isset($this->params['date_from']) && isset($this->params['date_to'])) {
            $query->whereBetween('transaction_date', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ]);
        }

        if (isset($this->params['outlet_id'])) {
            $query->where('outlet_id', $this->params['outlet_id']);
        }

        if (isset($this->params['tenant_id'])) {
            $query->where('tenant_id', $this->params['tenant_id']);
        }

        return $query->orderBy('transaction_date', 'desc')->get();
    }

    public function map($transaction): array
    {
        return [
            $transaction->transaction_number,
            $transaction->transaction_date ? date('Y-m-d H:i', strtotime($transaction->transaction_date)) : '',
            $transaction->outlet->name ?? '',
            $transaction->user->name ?? '',
            $transaction->customer->name ?? '-',
            (float)$transaction->subtotal,
            (float)$transaction->discount_amount,
            (float)$transaction->tax_amount,
            (float)$transaction->total_amount,
            $transaction->payment_method ?? '',
        ];
    }

    public function headings(): array
    {
        return [
            'No Transaksi',
            'Tanggal',
            'Outlet',
            'Kasir',
            'Pelanggan',
            'Subtotal',
            'Diskon',
            'Pajak',
            'Total',
            'Metode Pembayaran',
        ];
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
            'F' => '#,##0.00', // Subtotal
            'G' => '#,##0.00', // Diskon
            'H' => '#,##0.00', // Pajak
            'I' => '#,##0.00', // Total
        ];
    }

    public function title(): string
    {
        return 'Transaksi';
    }
}

class SalesReportTopProductsSheet extends BaseReportSheet implements \Maatwebsite\Excel\Concerns\WithStyles, \Maatwebsite\Excel\Concerns\ShouldAutoSize, \Maatwebsite\Excel\Concerns\WithColumnFormatting
{
    public function collection()
    {
        $dateFrom = $this->params['date_from'] ?? now()->subDays(30)->format('Y-m-d');
        $dateTo = $this->params['date_to'] ?? now()->format('Y-m-d');

        $query = TransactionItem::select(
                'products.id',
                'products.name',
                'products.sku',
                'categories.name as category_name',
                \Illuminate\Support\Facades\DB::raw('SUM(transaction_items.quantity) as total_sold'),
                \Illuminate\Support\Facades\DB::raw('SUM(transaction_items.total_price) as total_revenue')
            )
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('transactions.status', 'completed');

        if (isset($this->params['date_from']) && isset($this->params['date_to'])) {
            $query->whereBetween('transactions.transaction_date', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ]);
        }

        if (isset($this->params['outlet_id'])) {
            $query->where('transactions.outlet_id', $this->params['outlet_id']);
        }

        if (isset($this->params['tenant_id'])) {
            $query->where('transactions.tenant_id', $this->params['tenant_id']);
        }

        return $query->groupBy('products.id', 'products.name', 'products.sku', 'categories.name')
            ->orderBy('total_sold', 'desc')
            ->limit(50)
            ->get();
    }

    public function map($product): array
    {
        return [
            $product->sku ?? '',
            $product->name ?? '',
            $product->category_name ?? '',
            $product->total_sold ?? 0,
            (float)($product->total_revenue ?? 0),
        ];
    }

    public function headings(): array
    {
        return ['SKU', 'Nama Produk', 'Kategori', 'Terjual', 'Total Pendapatan'];
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
            'E' => '#,##0.00', // Total Pendapatan
        ];
    }

    public function title(): string
    {
        return 'Produk Terlaris';
    }
}


