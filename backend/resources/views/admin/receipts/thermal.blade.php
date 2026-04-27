<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #{{ $sale->sale_number }}</title>
    <style>
        /* ========================================
           THERMAL RECEIPT - 80mm POS PRINTER
           Optimized for Square/Shopify style
           ======================================== */
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', 'Consolas', monospace;
            font-size: 11px;
            line-height: 1.3;
            color: #000;
            background: #e5e5e5;
            padding: 10mm 0;
        }
        
        /* Receipt container - 72mm printable width */
        .receipt {
            width: 72mm;
            margin: 0 auto;
            background: white;
            padding: 4mm 3mm;
        }
        
        /* ========================================
           STORE HEADER
           ======================================== */
        
        .store-header {
            text-align: center;
            margin-bottom: 6px;
            padding-bottom: 5px;
            border-bottom: 1px dashed #333;
        }
        
        .store-name {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .store-address {
            font-size: 10px;
            line-height: 1.2;
            margin-bottom: 1px;
        }
        
        .store-phone {
            font-size: 10px;
            line-height: 1.2;
        }
        
        /* ========================================
           TRANSACTION INFO
           ======================================== */
        
        .sale-info {
            margin: 6px 0;
            font-size: 10px;
        }
        
        .sale-info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1px;
            line-height: 1.4;
        }
        
        .sale-info-label {
            font-weight: normal;
        }
        
        .sale-info-value {
            font-weight: bold;
        }
        
        /* ========================================
           SEPARATOR
           ======================================== */
        
        .separator {
            border-top: 1px dashed #333;
            margin: 5px 0;
        }
        
        /* ========================================
           ITEMS LIST - Compact POS Style
           ======================================== */
        
        .items-section {
            margin: 6px 0;
        }
        
        .item {
            margin-bottom: 3px;
            page-break-inside: avoid;
        }
        
        .item-line1 {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 1px;
        }
        
        .item-name {
            flex: 1;
            text-align: left;
            word-wrap: break-word;
        }
        
        .item-total {
            text-align: right;
            white-space: nowrap;
            margin-left: 8px;
        }
        
        .item-line2 {
            font-size: 10px;
            color: #000;
            padding-left: 2px;
        }
        
        /* ========================================
           TOTALS SECTION
           ======================================== */
        
        .totals {
            margin-top: 6px;
            padding-top: 5px;
            border-top: 1px solid #333;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
            font-size: 11px;
            line-height: 1.4;
        }
        
        .total-row.grand-total {
            font-size: 13px;
            font-weight: bold;
            margin-top: 3px;
            padding-top: 3px;
            border-top: 1px solid #333;
        }
        
        .total-label {
            text-align: left;
        }
        
        .total-value {
            text-align: right;
            font-weight: bold;
        }
        
        /* ========================================
           PAYMENT INFO
           ======================================== */
        
        .payment-info {
            margin-top: 6px;
            padding-top: 5px;
            border-top: 1px dashed #333;
            font-size: 10px;
        }
        
        .payment-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
            line-height: 1.4;
        }
        
        .payment-method {
            text-transform: uppercase;
            font-weight: bold;
        }
        
        .change-amount {
            font-size: 12px;
            font-weight: bold;
            color: #000;
        }
        
        /* ========================================
           FOOTER
           ======================================== */
        
        .footer {
            text-align: center;
            margin-top: 10px;
            padding-top: 6px;
            border-top: 1px dashed #333;
            font-size: 10px;
        }
        
        .thank-you {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 3px;
        }
        
        .footer-note {
            font-size: 9px;
            margin-top: 3px;
            color: #000;
        }
        
        /* ========================================
           PRINT OPTIMIZATION
           ======================================== */
        
        @media print {
            body {
                width: 72mm;
                padding: 0;
                margin: 0;
                background: white;
            }
            
            .receipt {
                width: 72mm;
                margin: 0;
                padding: 2mm 1mm;
            }
            
            .no-print {
                display: none !important;
            }
            
            /* Ensure no page breaks within items (already in main CSS) */
            
            /* Force black and white */
            * {
                color: #000 !important;
                background: white !important;
            }
        }
        
        /* ========================================
           SCREEN-ONLY: Print Button
           ======================================== */
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .print-button:hover {
            background: #1d4ed8;
        }
        
        @media print {
            .print-button {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Print Button (screen only) -->
    <button onclick="window.print()" class="print-button no-print">🖨️ Print Receipt</button>

    <div class="receipt">
        <!-- ========================================
             STORE HEADER
             ======================================== -->
        <div class="store-header">
            <div class="store-name">{{ \App\Models\Setting::get('store_name', 'Brilliant POS') }}</div>
            <div class="store-address">{{ \App\Models\Setting::get('store_address', '') }}</div>
            @if(\App\Models\Setting::get('store_phone', ''))
            <div class="store-phone">Tel: {{ \App\Models\Setting::get('store_phone', '') }}</div>
            @endif
        </div>

        <!-- ========================================
             SALE INFORMATION
             ======================================== -->
        <div class="sale-info">
            <div class="sale-info-row">
                <span class="sale-info-label">Receipt #:</span>
                <span class="sale-info-value">{{ $sale->sale_number }}</span>
            </div>
            <div class="sale-info-row">
                <span class="sale-info-label">Date:</span>
                <span class="sale-info-value">{{ $sale->sale_date->format('d/m/Y H:i') }}</span>
            </div>
            @if($sale->cashier)
            <div class="sale-info-row">
                <span class="sale-info-label">Cashier:</span>
                <span class="sale-info-value">{{ $sale->cashier->name }}</span>
            </div>
            @endif
            @if($sale->customer_name)
            <div class="sale-info-row">
                <span class="sale-info-label">Customer:</span>
                <span class="sale-info-value">{{ $sale->customer_name }}</span>
            </div>
            @endif
        </div>

        <!-- Separator -->
        <div class="separator"></div>

        <!-- ========================================
             ITEMS - Square/Shopify Style
             ======================================== -->
        <div class="items-section">
            @foreach($sale->items as $item)
            <div class="item">
                <div class="item-line1">
                    <span class="item-name">{{ $item->product->name ?? 'Product' }}</span>
                    <span class="item-total">₦{{ number_format($item->line_total ?? ($item->quantity * $item->unit_price), 2) }}</span>
                </div>
                <div class="item-line2">
                    {{ $item->quantity }} x ₦{{ number_format($item->unit_price, 2) }}
                </div>
            </div>
            @endforeach
        </div>

        <!-- ========================================
             TOTALS
             ======================================== -->
        <div class="totals">
            <div class="total-row">
                <span class="total-label">Subtotal</span>
                <span class="total-value">₦{{ number_format($sale->subtotal ?? $sale->total_amount, 2) }}</span>
            </div>

            @if(isset($sale->discount_amount) && $sale->discount_amount > 0)
            <div class="total-row">
                <span class="total-label">Discount</span>
                <span class="total-value">-₦{{ number_format($sale->discount_amount, 2) }}</span>
            </div>
            @endif

            @if(isset($sale->tax_amount) && $sale->tax_amount > 0)
            <div class="total-row">
                <span class="total-label">Tax</span>
                <span class="total-value">₦{{ number_format($sale->tax_amount, 2) }}</span>
            </div>
            @endif

            <div class="total-row grand-total">
                <span class="total-label">TOTAL</span>
                <span class="total-value">₦{{ number_format($sale->total_amount, 2) }}</span>
            </div>
        </div>

        <!-- ========================================
             PAYMENT
             ======================================== -->
        <div class="payment-info">
            <div class="payment-row">
                <span>Payment Method</span>
                <span class="payment-method">{{ strtoupper(str_replace('_', ' ', $sale->sale_type ?? 'CASH')) }}</span>
            </div>

            @if(in_array($sale->sale_type, ['cash', 'pos']))
            <div class="payment-row">
                <span>Paid</span>
                <span>₦{{ number_format($sale->amount_paid ?? $sale->total_amount, 2) }}</span>
            </div>

            @php
                $change = ($sale->amount_paid ?? $sale->total_amount) - $sale->total_amount;
            @endphp

            @if($change > 0)
            <div class="payment-row">
                <span>Change</span>
                <span class="change-amount">₦{{ number_format($change, 2) }}</span>
            </div>
            @endif
            @endif
        </div>

        <!-- ========================================
             FOOTER
             ======================================== -->
        <div class="footer">
            <div class="thank-you">Thank you for shopping!</div>
            <div>Please visit us again</div>
            <div class="footer-note">Printed: {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </div>

    <!-- ========================================
         AUTO-PRINT SCRIPT
         ======================================== -->
    <script>
        // Auto-trigger print dialog when page loads
        window.onload = function() {
            // Small delay to ensure page is fully rendered
            setTimeout(function() {
                window.print();
            }, 500);
        };

        // Keyboard shortcut: Ctrl+P or Cmd+P
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });

        // Auto-close after printing (optional - can be disabled)
        window.onafterprint = function() {
            // Uncomment to auto-close window after printing
            // setTimeout(function() { window.close(); }, 1000);
        };
    </script>
</body>
</html>
