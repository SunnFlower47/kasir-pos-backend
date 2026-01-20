<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt</title>
    <style>
        @page {
            margin: 0;
            size: 58mm auto;
        }

        body {
            font-family: 'Courier New', monospace;
            font-size: 16px;
            line-height: 1.3;
            margin: 0;
            padding: 1mm 2mm;
            width: 54mm;
            max-width: 54mm;
            color: #000;
            box-sizing: border-box;
        }

        .center {
            text-align: center;
        }

        .bold {
            font-weight: bold;
        }

        .separator {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }

        .row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
        }

        .item {
            margin: 4px 0;
        }

        .total {
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            margin: 8px 0;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="center">
        <div class="bold" style="font-size: 16px;">{{ $company['name'] }}</div>
        @if($company['address'])
            <div>{{ $company['address'] }}</div>
        @endif
        @if($company['phone'])
            <div>{{ $company['phone'] }}</div>
        @endif
    </div>

    <div class="separator"></div>

    <!-- Transaction Info -->
    <div class="center">
        <div class="bold">STRUK PENJUALAN</div>
        <div>{{ $transaction->transaction_number }}</div>
        <div>{{ \Carbon\Carbon::parse($transaction->transaction_date)->format('d/m/Y H:i') }}</div>
        <div>Kasir: {{ $transaction->user->name ?? 'Admin' }}</div>
        @if($transaction->customer)
            <div>{{ $transaction->customer->name }}</div>
        @endif
    </div>

    <div class="separator"></div>

    <!-- Items -->
    @foreach($transaction->transactionItems as $item)
        <div class="item">
            <div>{{ $item->product->name ?? 'Produk' }}</div>
            <div class="row">
                <span>{{ $item->quantity }} {{ $item->unit->symbol ?? $item->unit->name ?? $item->product->unit->name ?? '' }} x {{ $currency_symbol }}{{ number_format((float)$item->unit_price, 0, ',', '.') }}</span>
                <span>{{ $currency_symbol }}{{ number_format((float)$item->total_price, 0, ',', '.') }}</span>
            </div>
        </div>
    @endforeach

    <div class="separator"></div>

    <!-- Totals -->
    <div class="row">
        <span>Subtotal:</span>
        <span>{{ $currency_symbol }}{{ number_format((float)$transaction->subtotal, 0, ',', '.') }}</span>
    </div>

    @if($transaction->discount_amount > 0)
        <div class="row">
            <span>Diskon:</span>
            <span>-{{ $currency_symbol }}{{ number_format((float)$transaction->discount_amount, 0, ',', '.') }}</span>
        </div>
    @endif

    @if($tax_enabled && $transaction->tax_amount > 0)
        <div class="row">
            <span>Pajak:</span>
            <span>{{ $currency_symbol }}{{ number_format((float)$transaction->tax_amount, 0, ',', '.') }}</span>
        </div>
    @endif

    <div class="separator"></div>

    <div class="total">
        TOTAL: {{ $currency_symbol }}{{ number_format((float)$transaction->total_amount, 0, ',', '.') }}
    </div>

    <div class="row">
        <span>Bayar:</span>
        <span>{{ $currency_symbol }}{{ number_format((float)$transaction->paid_amount, 0, ',', '.') }}</span>
    </div>

    @if($transaction->change_amount > 0)
        <div class="row">
            <span>Kembalian:</span>
            <span>{{ $currency_symbol }}{{ number_format((float)$transaction->change_amount, 0, ',', '.') }}</span>
        </div>
    @endif

    <div class="separator"></div>

    <!-- Footer -->
    <div class="center">
        <div>{{ $receipt['header'] }}</div>
        <div style="margin-top: 8px; font-size: 12px;">{{ $receipt['footer'] }}</div>
        <div style="margin-top: 8px; font-size: 12px;">
            {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>
