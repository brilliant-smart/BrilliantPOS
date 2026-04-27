<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #{{ $sale->sale_number ?? $sale->id }}</title>
    <style>
        /* Thermal Printer Optimized (80mm width) */
        @page {
            size: 80mm auto;
            margin: 5mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            line-height: 1.4;
            color: #000;
            width: 70mm;
            margin: 0 auto;
            padding: 5mm;
        }
        
        .header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 1px dashed #000;
            padding-bottom: 8px;
        }
        
        .store-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .store-info {
            font-size: 9px;
            line-height: 1.3;
        }
        
        .receipt-title {
            font-size: 14px;
            font-weight: bold;
            margin: 8px 0;
            text-align: center;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 10px;
        }
        
        .items-table {
            width: 100%;
            margin: 10px 0;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 5px 0;
        }
        
        .item-row {
            margin-bottom: 8px;
        }
        
        .item-name {
            font-weight: bold;
            margin-bottom: 2px;
        }
        
        .item-details {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #333;
        }
        
        .totals {
            margin-top: 10px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .total-row.grand-total {
            font-size: 14px;
            font-weight: bold;
            border-top: 1px solid #000;
            border-bottom: 2px solid #000;
            padding: 5px 0;
            margin-top: 5px;
        }
        
        .payment-info {
            margin-top: 10px;
            border-top: 1px dashed #000;
            padding-top: 8px;
        }
        
        .footer {
            text-align: center;
            margin-top: 15px;
            border-top: 1px dashed #000;
            padding-top: 10px;
            font-size: 9px;
        }
        
        .thank-you {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        /* Print-specific styles */
        @media print {
            body {
                width: 70mm;
                padding: 0;
            }
            
            /* Hide print button when printing */
            .no-print {
                display: none !important;
            }
        }
        
        /* Print button (only visible on screen) */
        .print-button {
            position: fixed;
            top: 10px;
            right: 10px;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        .print-button:hover {
            background: #45a049;
        }
        
        @media print {
            .print-button {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Print Button (visible on screen only) -->
    <button onclick="window.print()" class="print-button no-print">🖨️ Print Receipt</button>
    
    <!-- Header -->
    <div class="header">
        <div class="store-name">{{ \App\Models\Setting::get('store_name', 'Brilliant POS') }}</div>
        <div class="store-info">
            {{ \App\Models\Setting::get('store_address', '') }}<br>
            @if(\App\Models\Setting::get('store_phone', ''))
            Tel: {{ \App\Models\Setting::get('store_phone', '') }}<br>
            @endif
            @if(\App\Models\Setting::get('store_email', ''))
            Email: {{ \App\Models\Setting::get('store_email', '') }}
            @endif
        </div>
    </div>

    <!-- Receipt Title -->
    <div class="receipt-title">SALES RECEIPT</div>

    <!-- Receipt Info -->
    <div class="info-row">
        <span>Receipt #:</span>
        <span><strong>{{ $sale->sale_number ?? 'SALE-' . $sale->id }}</strong></span>
    </div>
    <div class="info-row">
        <span>Date:</span>
        <span>{{ \Carbon\Carbon::parse($sale->sale_date)->format('d/m/Y H:i') }}</span>
    </div>
    @if($sale->customer_name)
    <div class="info-row">
        <span>Customer:</span>
        <span>{{ $sale->customer_name }}</span>
    </div>
    @endif
    @if($sale->user)
    <div class="info-row">
        <span>Cashier:</span>
        <span>{{ $sale->user->name }}</span>
    </div>
    @endif

    <!-- Items Table -->
    <div class="items-table">
        @foreach($sale->items as $item)
        <div class="item-row">
            <div style="flex: 1;">
                <div class="item-name">{{ $item->product->name ?? 'Product #' . $item->product_id }}</div>
                <div class="item-details">
                    <span>{{ $item->quantity }} x ₦{{ number_format($item->unit_price, 2) }}</span>
                    <span>₦{{ number_format($item->quantity * $item->unit_price, 2) }}</span>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <!-- Totals -->
    <div class="totals">
        <div class="total-row">
            <span>Subtotal:</span>
            <span>₦{{ number_format($sale->subtotal, 2) }}</span>
        </div>
        <div class="total-row grand-total">
            <span>TOTAL:</span>
            <span>₦{{ number_format($sale->total_amount, 2) }}</span>
        </div>
    </div>

    <!-- Payment Info -->
    <div class="payment-info">
        <div class="info-row">
            <span>Payment Method:</span>
            <span style="text-transform: uppercase;">{{ str_replace('_', ' ', $sale->payment_method) }}</span>
        </div>
        @if($sale->payment_method == 'cash')
        <div class="info-row">
            <span>Amount Paid:</span>
            <span>₦{{ number_format($sale->amount_paid ?? $sale->total_amount, 2) }}</span>
        </div>
        @if(($sale->amount_paid ?? $sale->total_amount) > $sale->total_amount)
        <div class="info-row">
            <span>Change:</span>
            <span>₦{{ number_format(($sale->amount_paid ?? $sale->total_amount) - $sale->total_amount, 2) }}</span>
        </div>
        @endif
        @endif
    </div>

    <!-- Footer -->
    <div class="footer">
        <div class="thank-you">THANK YOU FOR YOUR PATRONAGE!</div>
        <div>Visit us again soon</div>
        <div style="margin-top: 5px;">Printed: {{ now()->format('d/m/Y H:i:s') }}</div>
    </div>

    <!-- Auto-print script (optional - can be enabled/disabled) -->
    <script>
        // Optional: Auto-trigger print dialog when page loads
        // Uncomment the line below to enable auto-print
        // window.addEventListener('load', () => setTimeout(() => window.print(), 500));
        
        // Keyboard shortcut: Ctrl+P or Cmd+P
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>
