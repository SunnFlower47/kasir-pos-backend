<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - {{ $transaction->transaction_number }}</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.2;
            margin: 0;
            padding: 1mm 2mm;
            width: 54mm;
            max-width: 54mm;
            box-sizing: border-box;
        }
        .center { text-align: center; }
        .left { text-align: left; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .separator {
            border-top: 1px dashed #000;
            margin: 5px 0;
        }
        .item-row {
            margin: 2px 0;
            font-size: 11px;
        }
        .item-name {
            width: 100%;
            text-align: left;
            word-wrap: break-word;
            font-size: 11px;
        }
        .item-details {
            display: flex;
            justify-content: space-between;
            width: 100%;
            font-size: 10px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 2px 0;
            font-size: 11px;
            width: 100%;
        }
        .total-final {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            margin: 3px 0;
        }
        .header {
            margin-bottom: 10px;
        }
        .footer {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header center">
        <div class="bold" style="font-size: 14px;">{{ $company['name'] }}</div>
        @if($company['address'])
            <div style="font-size: 11px;">{{ $company['address'] }}</div>
        @endif
        @if($company['phone'])
            <div style="font-size: 11px;">Tel: {{ $company['phone'] }}</div>
        @endif
    </div>

    <div class="separator"></div>

    <!-- Transaction Info -->
    <div class="center">
        <div class="bold" style="font-size: 12px;">STRUK PENJUALAN</div>
        <div style="font-size: 11px;">{{ $transaction->transaction_number }}</div>
        <div style="font-size: 11px;">{{ \Carbon\Carbon::parse($transaction->transaction_date)->format('d/m/Y H:i') }}</div>
        <div style="font-size: 11px;">Kasir: {{ $transaction->user->name ?? 'Admin' }}</div>
        @if($transaction->customer)
            <div style="font-size: 11px;">Customer: {{ $transaction->customer->name }}</div>
        @endif
    </div>

    <div class="separator"></div>

    <!-- Items -->
    <div>
        @foreach($transaction->transactionItems as $item)
            <div class="item-row">
                <div class="item-name">{{ $item->product->name ?? 'Produk' }}</div>
                <div class="item-details">
                    <span>{{ $item->quantity }}x {{ $currency_symbol }}{{ number_format((float)$item->unit_price, 0, ',', '.') }}</span>
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
    </div>

    <div class="separator"></div>

    <!-- Totals -->
    <div>
        <div class="total-row">
            <span>Subtotal:</span>
            <span>{{ $currency_symbol }}{{ number_format((float)$transaction->subtotal, 0, ',', '.') }}</span>
        </div>

        @if($transaction->discount_amount > 0)
            <div class="total-row">
                <span>Diskon:</span>
                <span>-{{ $currency_symbol }}{{ number_format((float)$transaction->discount_amount, 0, ',', '.') }}</span>
            </div>
        @endif

        @if($tax_enabled && $transaction->tax_amount > 0)
            <div class="total-row">
                <span>Pajak ({{ $tax_rate }}%):</span>
                <span>{{ $currency_symbol }}{{ number_format((float)$transaction->tax_amount, 0, ',', '.') }}</span>
            </div>
        @endif

        <div class="separator"></div>

        <div class="total-final">
            TOTAL: {{ $currency_symbol }}{{ number_format((float)$transaction->total_amount, 0, ',', '.') }}
        </div>

        <div class="total-row">
            <span>Bayar ({{ ucfirst($transaction->payment_method) }}):</span>
            <span>{{ $currency_symbol }}{{ number_format((float)$transaction->paid_amount, 0, ',', '.') }}</span>
        </div>

        @if($transaction->change_amount > 0)
            <div class="total-row">
                <span>Kembalian:</span>
                <span>{{ $currency_symbol }}{{ number_format((float)$transaction->change_amount, 0, ',', '.') }}</span>
            </div>
        @endif
    </div>

    @if($transaction->customer && $transaction->customer->loyalty_points > 0)
        <div class="separator"></div>
        <div class="center">
            <div>Poin Loyalty: {{ number_format($transaction->customer->loyalty_points) }}</div>
            <div>Level: {{ ucfirst($transaction->customer->level) }}</div>
        </div>
    @endif

    <div class="separator"></div>

    <!-- Footer -->
    <div class="footer center">
        <div style="font-size: 11px;">{{ $receipt['header'] }}</div>
        <div style="margin-top: 5px; font-size: 10px;">{{ $receipt['footer'] }}</div>
        <div style="margin-top: 5px; font-size: 10px;">
            Dicetak: {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>
