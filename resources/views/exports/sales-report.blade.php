<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan</title>
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
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .summary-item {
            background: white;
            padding: 15px;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .summary-item h3 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 14px;
        }
        .summary-item .value {
            font-size: 20px;
            font-weight: bold;
            color: #2563eb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 11px;
        }
        th, td {
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background-color: #f1f5f9;
            color: #1e293b;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.05em;
        }
        tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            color: #666;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $company_name ?? 'Kasir POS' }}</h1>
        <p>{{ $company_address ?? '' }}</p>
        <p>{{ $company_phone ?? '' }}</p>
        <h2>Laporan Penjualan</h2>
        <p>Periode: {{ $params['date_from'] ?? 'Semua' }} s/d {{ $params['date_to'] ?? 'Sekarang' }}</p>
    </div>

    <div class="summary">
        <h2 style="margin-top: 0; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 15px; color: #1e293b;">Ringkasan</h2>
        <div class="summary-grid">
            <div class="summary-item">
                <h3>Total Transaksi</h3>
                <div class="value">{{ number_format($data['summary']['total_transactions'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="summary-item">
                <h3>Total Pendapatan</h3>
                <div class="value">Rp {{ number_format($data['summary']['total_revenue'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="summary-item">
                <h3>Rata-rata Transaksi</h3>
                <div class="value">Rp {{ number_format($data['summary']['avg_transaction_value'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="summary-item">
                <h3>Total Diskon</h3>
                <div class="value">Rp {{ number_format($data['summary']['total_discount'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="summary-item">
                <h3>Total Pajak</h3>
                <div class="value">Rp {{ number_format($data['summary']['total_tax'] ?? 0, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>

    @if(isset($data['transactions']['data']) && count($data['transactions']['data']) > 0)
    <div class="section">
        <h2 style="margin-bottom: 15px; color: #1e293b;">Daftar Transaksi</h2>
        <table>
            <thead>
                <tr>
                    <th>No Transaksi</th>
                    <th>Waktu</th>
                    <th>Outlet</th>
                    <th>Kasir</th>
                    <th>Pelanggan</th>
                    <th>Metode</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['transactions']['data'] as $transaction)
                <tr>
                    <td>{{ $transaction['transaction_number'] }}</td>
                    <td>{{ date('d/m/Y H:i', strtotime($transaction['transaction_date'])) }}</td>
                    <td>{{ $transaction['outlet']['name'] ?? '-' }}</td>
                    <td>{{ $transaction['user']['name'] ?? '-' }}</td>
                    <td>{{ $transaction['customer']['name'] ?? '-' }}</td>
                    <td style="text-transform: localize;">{{ ucfirst($transaction['payment_method']) }}</td>
                    <td style="text-align: right;">Rp {{ number_format($transaction['total_amount'], 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">
        <p>Dicetak pada: {{ $export_date ?? now()->format('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>

