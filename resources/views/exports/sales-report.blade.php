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
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
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
        <p>Periode: {{ $params['date_from'] ?? '' }} s/d {{ $params['date_to'] ?? '' }}</p>
    </div>

    <div class="summary">
        <h2 style="margin-top: 0;">Ringkasan</h2>
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
        </div>
    </div>

    <div class="footer">
        <p>Dicetak pada: {{ $export_date ?? now()->format('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>

