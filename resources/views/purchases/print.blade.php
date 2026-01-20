<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Invoice - {{ $purchase->invoice_number }}</title>
    <style>
        @media print {
            @page {
                margin: 0; /* Hilangkan margin halaman untuk sembunyikan header/footer browser */
                size: portrait;
            }
            body {
                margin: 0;
                padding: 1cm; /* Pindahkan margin ke body */
                -webkit-print-color-adjust: exact;
            }
            .no-print {
                display: none;
            }
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px; /* Slightly smaller for dot matrix density */
            line-height: 1.2;
            color: #000;
            background: #fff;
        }
        .container {
            width: 100%;
            max-width: 210mm; /* Standard width for Dot Matrix (A4/Letter Portrait) */
            margin: 0 auto;
            padding: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px dashed #000;
            padding-bottom: 10px;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .company-info {
            font-size: 12px;
        }
        .invoice-details {
            margin-top: 15px;
            text-align: left;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .invoice-title {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .meta-info {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 5px 15px;
        }
        .divider {
            border-bottom: 1px dashed #000;
            margin: 15px 0;
        }
        .row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }
        .col {
            flex: 1;
        }
        .section-title {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        thead {
            display: table-header-group; /* Repeats header on every page */
        }
        tr {
            page-break-inside: avoid; /* Prevents rows from being cut in half */
        }
        th {
            text-align: left;
            border-bottom: 1px dashed #000;
            border-top: 1px dashed #000;
            padding: 5px 0;
            text-transform: uppercase;
        }
        td {
            padding: 5px 0;
            vertical-align: top;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        
        .totals-box {
            margin-left: auto;
            width: 300px;
            margin-top: 10px;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
        }
        .totals-row.final {
            border-top: 1px dashed #000;
            border-bottom: 2px dashed #000;
            padding: 5px 0;
            margin-top: 5px;
            font-weight: bold;
            font-size: 14px;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 11px;
            border-top: 1px dashed #000;
            padding-top: 10px;
        }
        .notes-box {
            margin-top: 20px;
            border: 1px dashed #000;
            padding: 10px;
        }
    </style>
</head>
<body onload="window.print()">
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="company-name">{{ $company['name'] }}</div>
            <div class="company-info">{{ $company['address'] }}</div>
            @if($company['phone'])
                <div class="company-info">TEL: {{ $company['phone'] }}</div>
            @endif
        </div>

        <div class="invoice-details">
            <div>
                <div class="invoice-title">PURCHASE INVOICE</div>
                <div>NO: {{ $purchase->invoice_number }}</div>
                <div>STATUS: {{ strtoupper($purchase->status) }}</div>
            </div>
            <div style="text-align: right;">
                <div>DATE: {{ date('d/m/Y', strtotime($purchase->purchase_date)) }}</div>
                <div>PRINTED: {{ date('d/m/Y H:i') }}</div>
            </div>
        </div>

        <div class="divider"></div>

        <div class="row">
            <div class="col">
                <div class="section-title">SUPPLIER:</div>
                <div class="text-bold">{{ $purchase->supplier->name }}</div>
                <div>{{ $purchase->supplier->address }}</div>
                @if($purchase->supplier->phone)
                    <div>TEL: {{ $purchase->supplier->phone }}</div>
                @endif
            </div>
            <div class="col" style="text-align: right;">
                <div class="section-title">SHIP TO:</div>
                <div class="text-bold">{{ $purchase->outlet->name }}</div>
                <div>{{ $purchase->outlet->address }}</div>
            </div>
        </div>

        <div class="divider"></div>

        <!-- Items -->
        <table>
            <thead>
                <tr>
                    <th style="width: 40%">ITEM</th>
                    <th class="text-right" style="width: 15%">QTY</th>
                    <th class="text-right" style="width: 20%">PRICE</th>
                    <th class="text-right" style="width: 25%">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach($purchase->purchaseItems as $item)
                @php
                    $conversion = $item->conversion_factor > 0 ? $item->conversion_factor : 1;
                    $displayQty = $item->quantity / $conversion;
                    $displayPrice = $item->unit_price * $conversion;
                    $unitName = $item->unit ? $item->unit->name : ($item->product->unit->name ?? 'Pcs');
                @endphp
                <tr>
                    <td>
                        {{ $item->product->name }}<br>
                        <small>{{ $item->product->sku }}</small>
                    </td>
                    <td class="text-right">{{ (float)$displayQty }} {{ $unitName }}</td>
                    <td class="text-right">{{ number_format($displayPrice, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($item->total_price, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="divider"></div>

        <!-- Totals -->
        <div class="totals-box">
            <div class="totals-row">
                <span>SUBTOTAL</span>
                <span>{{ $currency_symbol }} {{ number_format($purchase->subtotal, 0, ',', '.') }}</span>
            </div>
            @if($purchase->tax_amount > 0)
            <div class="totals-row">
                <span>TAX</span>
                <span>{{ $currency_symbol }} {{ number_format($purchase->tax_amount, 0, ',', '.') }}</span>
            </div>
            @endif
            @if($purchase->discount_amount > 0)
            <div class="totals-row">
                <span>DISCOUNT</span>
                <span>-{{ $currency_symbol }} {{ number_format($purchase->discount_amount, 0, ',', '.') }}</span>
            </div>
            @endif
            <div class="totals-row final">
                <span>TOTAL</span>
                <span>{{ $currency_symbol }} {{ number_format($purchase->total_amount, 0, ',', '.') }}</span>
            </div>
            <div class="totals-row">
                <span>PAID</span>
                <span>{{ $currency_symbol }} {{ number_format($purchase->paid_amount, 0, ',', '.') }}</span>
            </div>
            <div class="totals-row">
                <span>REMAINING</span>
                <span>{{ $currency_symbol }} {{ number_format($purchase->remaining_amount, 0, ',', '.') }}</span>
            </div>
        </div>

        @if($purchase->notes)
        <div class="notes-box">
            <div class="section-title">NOTES:</div>
            {{ $purchase->notes }}
        </div>
        @endif

        <div class="footer">
            THANK YOU FOR YOUR BUSINESS
            <br>
            Please keep this invoice for your records.
        </div>
    </div>
</body>
</html>
