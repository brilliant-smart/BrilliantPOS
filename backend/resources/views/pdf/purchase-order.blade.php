<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Order - {{ $po->po_number }}</title>
    <style>
        /* === MODERN BRANDED DESIGN SYSTEM === */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', 'Helvetica', 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            font-size: 11px;
            line-height: 1.6;
            color: #2E2E2E;
            background: #FAFCFB;
        }
        
        .page-wrapper {
            padding: 25px 30px;
            max-width: 210mm;
            margin: 0 auto;
        }
        
        /* === HEADER SECTION === */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 35px;
            padding-bottom: 20px;
            border-bottom: 3px solid #2F8F5B;
        }
        
        .header-left {
            display: table-cell;
            width: 60%;
            vertical-align: top;
        }
        
        .header-right {
            display: table-cell;
            width: 40%;
            vertical-align: top;
            text-align: right;
        }
        
        .company-name {
            font-size: 26px;
            font-weight: bold;
            color: #1F5E3B;
            margin-bottom: 5px;
            letter-spacing: -0.5px;
        }
        
        .company-tagline {
            font-size: 10px;
            color: #6B7280;
            margin-bottom: 3px;
        }
        
        .document-title {
            font-size: 22px;
            font-weight: bold;
            color: #2F8F5B;
            margin-bottom: 8px;
        }
        
        .po-number {
            font-size: 14px;
            font-weight: bold;
            color: #1F5E3B;
            margin-bottom: 3px;
        }
        
        .po-date {
            font-size: 10px;
            color: #6B7280;
        }
        
        /* === INFORMATION GRID === */
        .info-section {
            margin-bottom: 25px;
        }
        
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #1F5E3B;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 2px solid #E6F2EC;
        }
        
        .info-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .info-row {
            display: table-row;
        }
        
        .info-label {
            display: table-cell;
            font-weight: 600;
            width: 35%;
            padding: 8px 12px;
            background-color: #E6F2EC;
            color: #1F5E3B;
            border-bottom: 1px solid #fff;
        }
        
        .info-value {
            display: table-cell;
            padding: 8px 12px;
            width: 65%;
            background-color: #FAFCFB;
            border-bottom: 1px solid #E2E8E4;
        }
        
        /* === STATUS BADGES === */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .status-pending { 
            background-color: #FEF3C7; 
            color: #92400E; 
            border: 1px solid #FDE68A;
        }
        
        .status-approved { 
            background-color: #DBEAFE; 
            color: #1E40AF; 
            border: 1px solid #BFDBFE;
        }
        
        .status-received { 
            background-color: #D1FAE5; 
            color: #065F46; 
            border: 1px solid #A7F3D0;
        }
        
        .status-completed { 
            background-color: #D1FAE5; 
            color: #065F46; 
            border: 1px solid #A7F3D0;
        }
        
        .status-cancelled { 
            background-color: #FEE2E2; 
            color: #991B1B; 
            border: 1px solid #FECACA;
        }
        
        .payment-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
        }
        
        .payment-paid { 
            background-color: #D1FAE5; 
            color: #065F46; 
        }
        
        .payment-partial { 
            background-color: #FEF3C7; 
            color: #92400E; 
        }
        
        .payment-unpaid { 
            background-color: #FEE2E2; 
            color: #991B1B; 
        }
        
        /* === ITEMS TABLE === */
        .items-section {
            margin-bottom: 25px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            border-radius: 6px;
            overflow: hidden;
        }
        
        thead {
            background: linear-gradient(135deg, #2F8F5B, #1F5E3B);
        }
        
        th {
            color: white;
            padding: 12px 10px;
            text-align: left;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        th:last-child {
            border-right: none;
        }
        
        tbody tr {
            border-bottom: 1px solid #E2E8E4;
        }
        
        tbody tr:nth-child(even) {
            background-color: #FAFCFB;
        }
        
        tbody tr:nth-child(odd) {
            background-color: #F7FBF9;
        }
        
        tbody tr:hover {
            background-color: #E6F2EC;
        }
        
        td {
            padding: 10px;
            font-size: 11px;
            color: #2E2E2E;
        }
        
        td.item-number {
            font-weight: 600;
            color: #6B7280;
            width: 5%;
        }
        
        td.product-name {
            font-weight: 500;
            color: #1F5E3B;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        /* === TOTALS SECTION === */
        .totals-wrapper {
            margin-top: 30px;
        }
        
        .total-section {
            float: right;
            width: 45%;
            background: #F7FBF9;
            border: 2px solid #E6F2EC;
            border-radius: 8px;
            padding: 15px 20px;
        }
        
        .total-row {
            display: table;
            width: 100%;
            padding: 6px 0;
        }
        
        .total-label {
            display: table-cell;
            font-weight: 600;
            color: #1F5E3B;
            font-size: 11px;
        }
        
        .total-value {
            display: table-cell;
            text-align: right;
            font-weight: 500;
            font-size: 11px;
        }
        
        .grand-total {
            border-top: 2px solid #2F8F5B;
            padding-top: 12px;
            margin-top: 12px;
        }
        
        .grand-total .total-label {
            font-size: 14px;
            font-weight: 700;
            color: #1F5E3B;
        }
        
        .grand-total .total-value {
            font-size: 16px;
            font-weight: 700;
            color: #2F8F5B;
        }
        
        .balance-due {
            background-color: #E6F2EC;
            padding: 8px 0;
            margin-top: 8px;
            border-radius: 4px;
        }
        
        .balance-due .total-label {
            color: #1F5E3B;
            font-weight: 700;
        }
        
        .balance-due .total-value {
            color: #2F8F5B;
            font-weight: 700;
        }
        
        /* === NOTES SECTION === */
        .notes-section {
            clear: both;
            margin-top: 30px;
            padding: 15px;
            background-color: #FAFCFB;
            border-left: 4px solid #2F8F5B;
            border-radius: 4px;
        }
        
        .notes-section h3 {
            font-size: 12px;
            color: #1F5E3B;
            margin-bottom: 8px;
        }
        
        .notes-section p {
            font-size: 10px;
            color: #6B7280;
            line-height: 1.6;
        }
        
        /* === FOOTER === */
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px solid #E6F2EC;
            text-align: center;
            font-size: 9px;
            color: #6B7280;
        }
        
        .footer-brand {
            font-weight: 600;
            color: #2F8F5B;
            margin-bottom: 3px;
        }
        
        .footer-tagline {
            color: #6B7280;
            margin-bottom: 3px;
        }
        
        .footer-timestamp {
            font-size: 8px;
            color: #9CA3AF;
            font-style: italic;
        }
        
        /* === PRINT OPTIMIZATIONS === */
        @media print {
            .page-wrapper {
                padding: 15px;
            }
            
            body {
                background: white;
            }
            
            .no-print {
                display: none;
            }
        }
        
        /* === PAGE BREAK CONTROL === */
        .page-break {
            page-break-after: always;
        }
        
        .avoid-break {
            page-break-inside: avoid;
        }
        
        .clearfix {
            clear: both;
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <!-- HEADER -->
        <div class="header">
            <div class="header-left">
                <div class="company-name">{{ \App\Models\Setting::get('store_name', 'Brilliant POS') }}</div>
                <div class="company-tagline">{{ \App\Models\Setting::get('store_tagline', 'Smart Shopping, Better Living') }}</div>
            </div>
            <div class="header-right">
                <div class="document-title">PURCHASE ORDER</div>
                <div class="po-number">{{ $po->po_number }}</div>
                <div class="po-date">{{ \Carbon\Carbon::parse($po->order_date)->format('F d, Y') }}</div>
            </div>
        </div>

        <!-- SUPPLIER INFORMATION -->
        <div class="info-section avoid-break">
            <h3 class="section-title">Supplier Information</h3>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Supplier Name</div>
                    <div class="info-value">{{ $po->supplier->name ?? 'N/A' }}</div>
                </div>
                @if($po->supplier && $po->supplier->contact_person)
                <div class="info-row">
                    <div class="info-label">Contact Person</div>
                    <div class="info-value">{{ $po->supplier->contact_person }}</div>
                </div>
                @endif
                @if($po->supplier && $po->supplier->phone)
                <div class="info-row">
                    <div class="info-label">Phone</div>
                    <div class="info-value">{{ $po->supplier->phone }}</div>
                </div>
                @endif
                @if($po->supplier && $po->supplier->email)
                <div class="info-row">
                    <div class="info-label">Email</div>
                    <div class="info-value">{{ $po->supplier->email }}</div>
                </div>
                @endif
            </div>
        </div>

        <!-- ORDER STATUS -->
        <div class="info-section avoid-break">
            <h3 class="section-title">Order Status</h3>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Order Status</div>
                    <div class="info-value">
                        <span class="status-badge status-{{ $po->status }}">{{ strtoupper($po->status) }}</span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Order Date</div>
                    <div class="info-value">{{ \Carbon\Carbon::parse($po->order_date)->format('F d, Y') }}</div>
                </div>
                @if($po->expected_delivery_date)
                <div class="info-row">
                    <div class="info-label">Expected Delivery</div>
                    <div class="info-value">{{ \Carbon\Carbon::parse($po->expected_delivery_date)->format('F d, Y') }}</div>
                </div>
                @endif
                <div class="info-row">
                    <div class="info-label">Payment Method</div>
                    <div class="info-value">{{ strtoupper(str_replace('_', ' ', $po->payment_method ?? 'CREDIT')) }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Payment Status</div>
                    <div class="info-value">
                        @php
                            $paymentStatus = strtoupper($po->payment_status ?? 'UNPAID');
                            $paymentClass = 'payment-unpaid';
                            if ($paymentStatus === 'PAID') {
                                $paymentClass = 'payment-paid';
                            } elseif ($paymentStatus === 'PARTIAL') {
                                $paymentClass = 'payment-partial';
                            }
                        @endphp
                        <span class="payment-badge {{ $paymentClass }}">{{ $paymentStatus }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ITEMS TABLE -->
        <div class="items-section avoid-break">
            <h3 class="section-title">Order Items</h3>
            <table>
                <thead>
                    <tr>
                        <th class="text-center">#</th>
                        <th>Product Name</th>
                        <th class="text-right">Qty Ordered</th>
                        <th class="text-right">Qty Received</th>
                        <th class="text-right">Unit Cost</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($po->items as $index => $item)
                    <tr>
                        <td class="item-number text-center">{{ $index + 1 }}</td>
                        <td class="product-name">{{ $item->product->name ?? 'Product #' . $item->product_id }}</td>
                        <td class="text-right">{{ $item->quantity_ordered }} {{ $item->unit_type ?? 'pcs' }}</td>
                        <td class="text-right">{{ $item->quantity_received ?? 0 }}</td>
                        <td class="text-right">₦{{ number_format($item->unit_cost, 2) }}</td>
                        <td class="text-right">₦{{ number_format($item->total_cost ?? ($item->quantity_ordered * $item->unit_cost), 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- TOTALS SECTION -->
        <div class="totals-wrapper avoid-break">
            <div class="total-section">
                <div class="total-row">
                    <span class="total-label">Subtotal:</span>
                    <span class="total-value">₦{{ number_format($po->subtotal, 2) }}</span>
                </div>
                @if($po->vat_amount > 0)
                <div class="total-row">
                    <span class="total-label">Tax/VAT:</span>
                    <span class="total-value">₦{{ number_format($po->vat_amount, 2) }}</span>
                </div>
                @endif
                @if($po->shipping_cost > 0)
                <div class="total-row">
                    <span class="total-label">Shipping:</span>
                    <span class="total-value">₦{{ number_format($po->shipping_cost, 2) }}</span>
                </div>
                @endif
                @if($po->discount_amount > 0)
                <div class="total-row">
                    <span class="total-label">Discount:</span>
                    <span class="total-value">-₦{{ number_format($po->discount_amount, 2) }}</span>
                </div>
                @endif
                <div class="total-row grand-total">
                    <span class="total-label">TOTAL:</span>
                    <span class="total-value">₦{{ number_format($po->total_amount, 2) }}</span>
                </div>
                @if($po->amount_paid > 0)
                <div class="total-row">
                    <span class="total-label">Paid:</span>
                    <span class="total-value">₦{{ number_format($po->amount_paid, 2) }}</span>
                </div>
                <div class="total-row balance-due">
                    <span class="total-label">Balance Due:</span>
                    <span class="total-value">₦{{ number_format($po->total_amount - $po->amount_paid, 2) }}</span>
                </div>
                @endif
            </div>
        </div>

        <div class="clearfix"></div>

        <!-- NOTES SECTION -->
        @if($po->notes)
        <div class="notes-section avoid-break">
            <h3>Additional Notes</h3>
            <p>{{ $po->notes }}</p>
        </div>
        @endif

        <!-- FOOTER -->
        <div class="footer">
            <div class="footer-brand">{{ \App\Models\Setting::get('store_name', 'Brilliant POS') }}</div>
            <div class="footer-tagline">{{ \App\Models\Setting::get('store_tagline', 'Smart Shopping, Better Living') }}</div>
            <div class="footer-timestamp">Generated on {{ now()->format('l, F d, Y \a\t H:i:s') }}</div>
        </div>
    </div>
</body>
</html>
