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
            margin-bottom: 30px;
        }
        .section h2 {
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
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
        <p>{{ $company_address ?? '' }}</p>
        <h2>Laporan Keuangan Komprehensif</h2>
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
                <th>Total Pengeluaran</th>
                <td>Rp {{ number_format($data['summary']['total_expenses'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <th>Total HPP</th>
                <td>Rp {{ number_format($data['summary']['total_cogs'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <th>Laba Kotor</th>
                <td>Rp {{ number_format($data['summary']['gross_profit'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <th>Laba Bersih</th>
                <td>Rp {{ number_format($data['summary']['net_profit'] ?? 0, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Dicetak pada: {{ $export_date ?? now()->format('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>

