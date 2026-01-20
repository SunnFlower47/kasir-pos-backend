<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan Komprehensif</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            color: #333;
            font-size: 24px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section h2 {
            color: #1e293b;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 8px;
            font-size: 16px;
            margin-bottom: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        th, td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: bold;
            width: 40%;
        }
        td {
            color: #0f172a;
        }
        tr:last-child th, tr:last-child td {
            border-bottom: none;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            color: #94a3b8;
            font-size: 10px;
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $company_name ?? 'Kasir POS' }}</h1>
        <p>{{ $company_address ?? '' }}</p>
        <p>{{ $company_phone ?? '' }}</p>
        <h2>Laporan Keuangan Komprehensif</h2>
        <p>Periode: {{ $params['date_from'] ?? 'Semua' }} s/d {{ $params['date_to'] ?? 'Sekarang' }}</p>
    </div>

    <!-- Summary Section -->
    <div class="section">
        <h2>Ringkasan Eksekutif</h2>
        <table>
            <tr>
                <th>Total Pendapatan</th>
                <td>Rp {{ number_format($data['summary']['total_revenue'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <th>Total Pengeluaran (Operasional + Pembelian)</th>
                <td>Rp {{ number_format($data['summary']['total_expenses'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <th>Total HPP (Harga Pokok Penjualan)</th>
                <td>Rp {{ number_format($data['summary']['total_cogs'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <th style="background-color: #e0f2fe;">Laba Kotor</th>
                <td style="font-weight: bold; color: #0284c7;">Rp {{ number_format($data['summary']['gross_profit'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <th style="background-color: #dcfce7;">Laba Bersih</th>
                <td style="font-weight: bold; color: #16a34a;">Rp {{ number_format($data['summary']['net_profit'] ?? 0, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <!-- Revenue Section -->
    <div class="section">
        <h2>Detail Pendapatan</h2>
        <table>
             <tr>
                <th>Total Pendapatan Kotor</th>
                <td>Rp {{ number_format($data['revenue']['total'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <th>Total Transaksi</th>
                <td>{{ number_format($data['revenue']['transaction_count'] ?? 0, 0, ',', '.') }} Transaksi</td>
            </tr>
            <tr>
                <th>Rata-rata Nilai Transaksi</th>
                <td>Rp {{ number_format($data['revenue']['avg_transaction_value'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <th>Total Refund (Pengembalian)</th>
                <td style="color: #ef4444;">- Rp {{ number_format($data['revenue']['refunds'] ?? 0, 0, ',', '.') }}</td>
            </tr>
             <tr>
                <th style="background-color: #eef2ff;">Pendapatan Bersih</th>
                <td style="font-weight: bold; color: #4f46e5;">Rp {{ number_format($data['revenue']['net_revenue'] ?? 0, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <!-- Expenses Section -->
     <div class="section">
        <h2>Detail Pengeluaran</h2>
        <table>
             <tr>
                <th>Pengeluaran Pembelian Stok</th>
                <td>Rp {{ number_format($data['expenses']['purchase_expenses'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <th>Jumlah Pembelian</th>
                <td>{{ $data['expenses']['purchase_count'] ?? 0 }} Transaksi</td>
            </tr>
             <tr>
                <th>Pengeluaran Operasional</th>
                <td>Rp {{ number_format($data['expenses']['operational_expenses'] ?? 0, 0, ',', '.') }}</td>
            </tr>
             <tr>
                <th style="background-color: #fef2f2;">Total Pengeluaran</th>
                <td style="font-weight: bold; color: #dc2626;">Rp {{ number_format($data['expenses']['total'] ?? 0, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <!-- Profit Analysis -->
    <div class="section">
        <h2>Analisis Profitabilitas</h2>
        <table>
             <tr>
                <th>Margin Laba Kotor</th>
                <td>{{ number_format($data['profit_loss']['gross_profit_margin'] ?? 0, 2, ',', '.') }}%</td>
            </tr>
             <tr>
                <th>Margin Laba Bersih</th>
                <td>{{ number_format($data['profit_loss']['net_profit_margin'] ?? 0, 2, ',', '.') }}%</td>
            </tr>
            <tr>
                <th>Status</th>
                <td>
                    @if($data['profit_loss']['is_profitable'])
                        <span style="color: #16a34a; font-weight: bold;">MENGUNTUNGKAN (PROFIT)</span>
                    @else
                        <span style="color: #dc2626; font-weight: bold;">RUGI (LOSS)</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Dicetak pada: {{ $export_date ?? now()->format('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>

