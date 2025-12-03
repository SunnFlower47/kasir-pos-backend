<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Enhanced</title>
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
            margin-bottom: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
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
        <p>Laporan Enhanced Analytics</p>
        <p>Periode: {{ $params['date_from'] ?? '' }} s/d {{ $params['date_to'] ?? '' }}</p>
    </div>

    <div class="section">
        <h2>Ringkasan</h2>
        <table>
            <tr>
                <th>Total Pendapatan</th>
                <td>Rp {{ number_format($data['summary']['total_revenue'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <th>Total Transaksi</th>
                <td>{{ number_format($data['summary']['total_transactions'] ?? 0, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Dicetak pada: {{ $export_date ?? now()->format('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>

