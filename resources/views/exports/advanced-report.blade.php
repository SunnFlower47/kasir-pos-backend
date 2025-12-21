<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Advanced - Business Intelligence</title>
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
        <p>Business Intelligence Report</p>
        <p>Periode: {{ $params['date_from'] ?? '' }} s/d {{ $params['date_to'] ?? '' }}</p>
    </div>

    <div class="section">
        <h2>Key Performance Indicators</h2>
        <table>
            <tr>
                <th>Total Revenue</th>
                <td>Rp {{ number_format((float)($data['kpis']['revenue']['net_revenue'] ?? $data['kpis']['revenue']['total'] ?? 0), 0, ',', '.') }}</td>
            </tr>
            <tr>
                <th>Total Transactions</th>
                <td>{{ number_format((float)($data['kpis']['transactions']['current'] ?? 0), 0, ',', '.') }}</td>
            </tr>
            <tr>
                <th>Average Transaction Value</th>
                <td>Rp {{ number_format((float)($data['kpis']['avg_transaction_value']['current'] ?? 0), 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Dicetak pada: {{ $export_date ?? now()->format('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>

