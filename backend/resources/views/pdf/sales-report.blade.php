<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', 'Helvetica', 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 11px;
            line-height: 1.6;
            color: #2E2E2E;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #2F8F5B;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #1F5E3B;
            margin-bottom: 5px;
        }
        
        .report-title {
            font-size: 18px;
            color: #2F8F5B;
            margin-top: 10px;
        }
        
        .report-period {
            font-size: 12px;
            color: #6B7280;
            margin-top: 5px;
        }
        
        .summary-section {
            margin-bottom: 25px;
            background: #F7FBF9;
            border: 2px solid #E6F2EC;
            border-radius: 8px;
            padding: 15px;
        }
        
        .summary-title {
            font-size: 14px;
            font-weight: bold;
            color: #1F5E3B;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 2px solid #E6F2EC;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 15px;
        }
        
        .summary-item {
            text-align: center;
            padding: 10px;
            background: white;
            border-radius: 4px;
        }
        
        .summary-label {
            font-size: 10px;
            color: #6B7280;
            margin-bottom: 5px;
        }
        
        .summary-value {
            font-size: 16px;
            font-weight: bold;
            color: #1F5E3B;
        }
        
        .summary-value.profit {
            color: #2F8F5B;
            font-size: 18px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        thead {
            background: linear-gradient(135deg, #2F8F5B, #1F5E3B);
        }
        
        th {
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
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
        
        td {
            padding: 10px 8px;
            font-size: 10px;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 15px;
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
        
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="company-name">{{ \App\Models\Setting::get('store_name', 'Brilliant POS') }}</div>
        <div class="report-title">Sales Report</div>
        <div class="report-period">
            Period: {{ \Carbon\Carbon::parse($period['start'])->format('M d, Y') }} 
            to {{ \Carbon\Carbon::parse($period['end'])->format('M d, Y') }}
        </div>
        <div class="report-period">Generated: {{ now()->format('F d, Y H:i:s') }}</div>
    </div>

    <!-- Summary Section -->
    <div class="summary-section">
        <div class="summary-title">Summary Metrics</div>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-label">Total Sales</div>
                <div class="summary-value">{{ $summary['total_sales'] }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Total Revenue</div>
                <div class="summary-value">₦{{ number_format($summary['total_revenue'], 2) }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Total Profit</div>
                <div class="summary-value profit">₦{{ number_format($summary['total_profit'], 2) }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Profit Margin</div>
                <div class="summary-value">{{ number_format($summary['profit_margin'], 2) }}%</div>
            </div>
        </div>
    </div>

    <!-- Sales Table -->
    <table>
        <thead>
            <tr>
                <th>Sale #</th>
                <th>Date</th>
                <th>Department</th>
                <th>Cashier</th>
                <th>Type</th>
                <th class="text-right">Amount</th>
                <th class="text-right">COGS</th>
                <th class="text-right">Profit</th>
                <th class="text-right">Margin%</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sales as $sale)
            @php
                $profitMargin = $sale->total_amount > 0 
                    ? ($sale->gross_profit / $sale->total_amount) * 100 
                    : 0;
            @endphp
            <tr>
                <td>{{ $sale->sale_number }}</td>
                <td>{{ $sale->sale_date->format('M d, Y') }}</td>
                <td>{{ $sale->department ? $sale->department->name : 'N/A' }}</td>
                <td>{{ $sale->cashier ? $sale->cashier->name : 'N/A' }}</td>
                <td>{{ strtoupper($sale->sale_type) }}</td>
                <td class="text-right">₦{{ number_format($sale->total_amount, 2) }}</td>
                <td class="text-right">₦{{ number_format($sale->cost_of_goods_sold ?? 0, 2) }}</td>
                <td class="text-right">₦{{ number_format($sale->gross_profit ?? 0, 2) }}</td>
                <td class="text-right">{{ number_format($profitMargin, 2) }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Totals Row -->
    <table style="margin-top: -20px;">
        <thead>
            <tr style="background: #1F5E3B;">
                <th colspan="5" style="text-align: right;">GRAND TOTAL:</th>
                <th class="text-right">₦{{ number_format($summary['total_revenue'], 2) }}</th>
                <th class="text-right">₦{{ number_format($summary['total_cogs'], 2) }}</th>
                <th class="text-right">₦{{ number_format($summary['total_profit'], 2) }}</th>
                <th class="text-right">{{ number_format($summary['profit_margin'], 2) }}%</th>
            </tr>
        </thead>
    </table>

    <!-- Footer -->
    <div class="footer">
        <div class="footer-brand">{{ \App\Models\Setting::get('store_name', 'Brilliant POS') }}</div>
        <div>{{ \App\Models\Setting::get('store_tagline', 'Smart Shopping, Better Living') }}</div>
        <div>This is a computer-generated report</div>
    </div>
</body>
</html>
