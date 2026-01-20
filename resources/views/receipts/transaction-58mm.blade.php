<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt 58mm</title>
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
            padding: 1mm;
            width: 56mm;
            max-width: 56mm;
            color: #000;
            box-sizing: border-box;
        }

        .center {
            text-align: center;
        }

        .left {
            text-align: left;
        }

        .right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .separator {
            border-top: 1px dashed #000;
            margin: 4px 0;
            width: 100%;
        }

        .row {
            display: flex;
            justify-content: space-between;
            margin: 2px 0;
            font-size: 14px;
        }

        .item {
            margin: 3px 0;
            font-size: 14px;
        }

        .item-name {
            font-size: 14px;
            margin-bottom: 1px;
        }

        .item-details {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
        }

        .total {
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            margin: 6px 0;
        }

        .header {
            font-size: 15px;
            margin-bottom: 4px;
        }

        .footer {
            font-size: 12px;
            margin-top: 6px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="center header">
        <div class="bold" style="font-size: 14px;">{{ $company['name'] }}</div>
        @if($company['address'])
            <div style="font-size: 13px;">{{ $company['address'] }}</div>
        @endif
        @if($company['phone'])
            <div style="font-size: 13px;">{{ $company['phone'] }}</div>
        @endif
    </div>

    <div class="separator"></div>

    <!-- Transaction Info -->
    <div class="center">
        <div class="bold">STRUK PENJUALAN</div>
        <div style="font-size: 13px;">{{ $transaction->transaction_number }}</div>
        <div style="font-size: 13px;">{{ \Carbon\Carbon::parse($transaction->transaction_date)->format('d/m/Y H:i') }}</div>
        <div style="font-size: 13px;">Kasir: {{ $transaction->user->name ?? 'Admin' }}</div>
        @if($transaction->customer)
            <div style="font-size: 13px;">{{ $transaction->customer->name }}</div>
        @endif
    </div>

    <div class="separator"></div>

    <!-- Items -->
    @foreach($transaction->transactionItems as $item)
        <div class="item">
            <div class="item-name">{{ $item->product->name ?? 'Produk' }}</div>
            <div class="item-details">
                <span>{{ $item->quantity }} {{ $item->unit->symbol ?? $item->unit->name ?? $item->product->unit->name ?? '' }} x {{ $currency_symbol }}{{ number_format((float)$item->unit_price, 0, ',', '.') }}</span>
                <span>{{ $currency_symbol }}{{ number_format((float)$item->total_price, 0, ',', '.') }}</span>
            </div>
            @if($item->discount_amount > 0)
                <div class="item-details">
                    <span>  Diskon</span>
                    <span>-{{ $currency_symbol }}{{ number_format((float)$item->discount_amount, 0, ',', '.') }}</span>
                </div>
            @endif
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
    <div class="center footer">
        <div>{{ $receipt['header'] }}</div>
        <div style="margin-top: 4px; font-size: 11px;">{{ $receipt['footer'] }}</div>
        <div style="margin-top: 4px; font-size: 11px;">
            {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>
