<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Http\Request;
// PDF library - will be loaded dynamically if available

class PurchaseOrderController extends Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;
    protected $purchaseOrderService;

    public function __construct(PurchaseOrderService $purchaseOrderService)
    {
        $this->purchaseOrderService = $purchaseOrderService;
    }

    /**
     * Display a listing of purchase orders
     * GET /api/purchase-orders
     */
    public function index(Request $request)
    {
        $query = PurchaseOrder::with(['supplier', 'creator']);

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Payment status filter
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // Supplier filter
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Date range
        if ($request->filled('start_date')) {
            $query->whereDate('order_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('order_date', '<=', $request->end_date);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $purchaseOrders = $query->latest('order_date')
            ->paginate($request->input('per_page', 15));

        return response()->json($purchaseOrders);
    }

    /**
     * Get price comparison data for products
     * POST /api/purchase-orders/price-comparison
     */
    public function getPriceComparison(Request $request)
    {
        $validated = $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        $products = \App\Models\Product::with(['priceHistory' => function($query) {
            $query->where('change_type', 'purchase')
                  ->latest('changed_at')
                  ->limit(3);
        }])
        ->whereIn('id', $validated['product_ids'])
        ->get();

        $comparison = $products->map(function($product) {
            $lastPrice = $product->last_purchase_price ?? 0;
            $history = $product->priceHistory->map(function($record) {
                return [
                    'price' => $record->new_price,
                    'date' => $record->changed_at,
                    'supplier' => $record->supplier_name,
                    'reference' => $record->reference_number,
                ];
            });

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'current_cost_price' => $product->cost_price,
                'last_purchase_price' => $lastPrice,
                'price_history' => $history,
            ];
        });

        return response()->json([
            'price_comparison' => $comparison,
        ]);
    }

    /**
     * Store a new purchase order
     * POST /api/purchase-orders
     */
    public function store(Request $request)
    {
        // Convert month/year expiry dates to last day of month for all items
        if ($request->has('items')) {
            $items = $request->items;
            foreach ($items as $index => $item) {
                if (!empty($item['expiry_date'])) {
                    $items[$index]['expiry_date'] = $this->convertToLastDayOfMonth($item['expiry_date']);
                }
            }
            $request->merge(['items' => $items]);
        }

        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'nullable|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'payment_method' => 'nullable|in:cash,bank_transfer,cheque,card,credit,credit_7,credit_14,credit_30,credit_60',
            'payment_due_date' => 'nullable|date',
            'status' => 'nullable|in:draft,pending',
            'shipping_cost' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_ordered' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.unit_type' => 'nullable|string|in:piece,carton,box,pack,dozen,kg,liter,meter',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.notes' => 'nullable|string',
            'items.*.batch_number' => 'nullable|string|max:255',
            'items.*.manufacturing_date' => 'nullable|date|before_or_equal:today',
            'items.*.expiry_date' => 'nullable|date',
        ]);

        try {
            $po = $this->purchaseOrderService->createPurchaseOrder($validated, $request->user());

            return response()->json([
                'message' => 'Purchase order created successfully',
                'purchase_order' => $po,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create purchase order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified purchase order
     * GET /api/purchase-orders/{purchaseOrder}
     */
    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load([
            'items.product',
            'supplier',
            'creator',
            'approver',
            'receiver'
        ]);

        return response()->json($purchaseOrder);
    }

    /**
     * Update purchase order
     * PUT /api/purchase-orders/{purchaseOrder}
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('update', $purchaseOrder);

        // Only draft and pending POs can be edited
        if (!in_array($purchaseOrder->status, ['draft', 'pending'])) {
            return response()->json([
                'message' => "Cannot edit purchase order with status: {$purchaseOrder->status}",
            ], 422);
        }

        $validated = $request->validate([
            'supplier_id' => 'sometimes|required|exists:suppliers,id',
            'expected_delivery_date' => 'nullable|date',
            'shipping_cost' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $purchaseOrder->update($validated);

        return response()->json([
            'message' => 'Purchase order updated successfully',
            'purchase_order' => $purchaseOrder->load(['items.product', 'supplier']),
        ]);
    }

    /**
     * Approve purchase order
     * POST /api/purchase-orders/{purchaseOrder}/approve
     */
    public function approve(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('approve', $purchaseOrder);

        $user = request()->user();

        // PO must be in draft or pending status to be approved
        if (!in_array($purchaseOrder->status, ['draft', 'pending'])) {
            return response()->json([
                'message' => "Purchase order with status '{$purchaseOrder->status}' cannot be approved",
            ], 422);
        }

        try {
            $po = $this->purchaseOrderService->approvePurchaseOrder($purchaseOrder, $user);

            return response()->json([
                'message' => 'Purchase order approved successfully',
                'purchase_order' => $po,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to approve purchase order',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reject purchase order
     * POST /api/purchase-orders/{purchaseOrder}/reject
     */
    public function reject(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('reject', $purchaseOrder);

        $validated = $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        $purchaseOrder->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Purchase order rejected',
            'purchase_order' => $purchaseOrder,
        ]);
    }

    /**
     * Cancel purchase order
     * POST /api/purchase-orders/{purchaseOrder}/cancel
     */
    public function cancel(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('cancel', $purchaseOrder);

        $user = $request->user();

        // Only draft, pending, or approved POs can be cancelled (not received or completed)
        if (in_array($purchaseOrder->status, ['received', 'completed', 'cancelled'])) {
            return response()->json([
                'message' => "Cannot cancel purchase order with status: {$purchaseOrder->status}",
            ], 422);
        }

        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:500',
        ]);

        $purchaseOrder->update([
            'status' => 'cancelled',
            'notes' => ($purchaseOrder->notes ? $purchaseOrder->notes . "\n\n" : '') .
                      "CANCELLED by {$user->name} on " . now()->format('Y-m-d H:i:s') .
                      "\nReason: {$validated['cancellation_reason']}",
        ]);

        return response()->json([
            'message' => 'Purchase order cancelled successfully',
            'purchase_order' => $purchaseOrder->load(['items.product', 'supplier']),
        ]);
    }

    /**
     * Receive goods (full or partial)
     * POST /api/purchase-orders/{purchaseOrder}/receive
     */
    public function receiveGoods(Request $request, PurchaseOrder $purchaseOrder)
    {
        // Log incoming request for debugging
        \Log::info('Receive goods request', [
            'po_id' => $purchaseOrder->id,
            'po_status' => $purchaseOrder->status,
            'payment_method' => $purchaseOrder->payment_method,
            'payment_status' => $purchaseOrder->payment_status,
            'request_data' => $request->all(),
        ]);

        // Payment workflow check
        // For CREDIT purchases: Allow receiving goods (pay later)
        // For CASH/IMMEDIATE purchases: Require payment first
        $paymentMethod = $purchaseOrder->payment_method;
        $isCreditPurchase = in_array($paymentMethod, ['credit', 'credit_7', 'credit_14', 'credit_30', 'credit_60']);
        $isPaid = in_array($purchaseOrder->payment_status, ['paid', 'partially_paid']);

        // Only enforce payment for non-credit purchases
        if (!$isCreditPurchase && !$isPaid) {
            \Log::warning('Payment required before receiving goods', [
                'po_id' => $purchaseOrder->id,
                'payment_method' => $paymentMethod,
                'payment_status' => $purchaseOrder->payment_status,
            ]);

            return response()->json([
                'message' => 'Payment must be recorded before receiving goods for cash/immediate purchases.',
                'action_required' => 'Record payment to proceed with receiving goods',
                'payment_status' => $purchaseOrder->payment_status ?? 'unpaid',
                'payment_method' => $paymentMethod,
                'total_amount' => $purchaseOrder->total_amount,
            ], 422);
        }

        try {
            // Convert month/year expiry dates to last day of month for all items
            if ($request->has('items')) {
                $items = $request->items;
                foreach ($items as $index => $item) {
                    if (!empty($item['expiry_date'])) {
                        $items[$index]['expiry_date'] = $this->convertToLastDayOfMonth($item['expiry_date']);
                    }
                }
                $request->merge(['items' => $items]);
            }

            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity_received' => 'required|integer|min:1',
                'items.*.batch_number' => 'nullable|string|max:255',
                'items.*.manufacturing_date' => 'nullable|date|before_or_equal:today',
                'items.*.expiry_date' => 'nullable|date',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation failed for receive goods', [
                'errors' => $e->errors(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            $po = $this->purchaseOrderService->receiveGoods(
                $purchaseOrder,
                $validated['items'],
                $request->user()
            );

            \Log::info('Goods received successfully', [
                'po_id' => $po->id,
                'po_number' => $po->po_number,
            ]);

            return response()->json([
                'message' => 'Goods received successfully',
                'purchase_order' => $po,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to receive goods', [
                'po_id' => $purchaseOrder->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to receive goods',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Record payment for purchase order
     * POST /api/purchase-orders/{purchaseOrder}/record-payment
     */
    public function recordPayment(Request $request, PurchaseOrder $purchaseOrder)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|in:cash,bank_transfer,cheque,card,credit,credit_7,credit_14,credit_30,credit_60',
            'payment_date' => 'nullable|date',
            'reference' => 'nullable|string|max:255',
        ]);

        try {
            // Handle credit payment method
            // Credit means "pay later" - we record it but don't add to amount_paid yet
            $paymentMethod = $validated['payment_method'] ?? $purchaseOrder->payment_method;
            $isCredit = $paymentMethod === 'credit';

            // Update amount paid (only if not credit)
            $currentPaid = $purchaseOrder->amount_paid ?? 0;
            $newPaid = $isCredit ? $currentPaid : ($currentPaid + $validated['amount']);

            // Determine payment status
            $paymentStatus = 'unpaid';
            if ($isCredit) {
                // Credit is recorded but marked as partially_paid to allow receiving goods
                $paymentStatus = 'partially_paid';
            } elseif ($newPaid >= $purchaseOrder->total_amount) {
                $paymentStatus = 'paid';
            } elseif ($newPaid > 0) {
                $paymentStatus = 'partially_paid';
            }

            // Calculate payment due date for credit purchases (30 days default)
            $paymentDueDate = $purchaseOrder->payment_due_date;
            if ($isCredit && !$paymentDueDate) {
                $paymentDueDate = now()->addDays(30);
            }

            $purchaseOrder->update([
                'amount_paid' => $newPaid,
                'payment_status' => $paymentStatus,
                'payment_method' => $paymentMethod,
                'payment_date' => $validated['payment_date'] ?? now(),
                'payment_due_date' => $paymentDueDate,
            ]);

            // TODO: Create payment transaction record

            $message = $isCredit
                ? 'Credit payment recorded. Payment due: ' . ($paymentDueDate ? $paymentDueDate->format('Y-m-d') : 'N/A')
                : 'Payment recorded successfully';

            return response()->json([
                'message' => $message,
                'purchase_order' => $purchaseOrder->load(['items.product', 'supplier']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to record payment',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Export purchase order as PDF
     * WEB ROUTE: GET /admin/purchase-orders/{purchaseOrder}/pdf
     * Authentication handled by auth.token middleware
     */
    public function exportPdf(Request $request, PurchaseOrder $purchaseOrder)
    {
        // Load relationships
        $purchaseOrder->load(['items.product', 'supplier', 'creator', 'approver', 'receiver']);

        // Check if DomPDF is available
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.purchase-order', ['po' => $purchaseOrder]);
            return $pdf->download("PO-{$purchaseOrder->po_number}.pdf");
        }

        // Fallback: Return HTML view that can be printed to PDF using browser
        return view('pdf.purchase-order', ['po' => $purchaseOrder]);
    }

    /**
     * Export all purchase orders to Excel/CSV
     * GET /api/purchase-orders/export
     */
    public function exportAll(Request $request)
    {
        $format = $request->get('format', 'csv'); // csv or excel

        $purchaseOrders = PurchaseOrder::with(['supplier', 'items.product', 'creator'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->exportToCsv($purchaseOrders);
    }

    /**
     * Export purchase orders to CSV
     */
    private function exportToCsv($purchaseOrders)
    {
        $filename = 'purchase-orders-' . now()->format('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() use ($purchaseOrders) {
            $file = fopen('php://output', 'w');

            // Add BOM for Excel UTF-8 support
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header row
            fputcsv($file, [
                'PO Number',
                'Supplier',
                'Order Date',
                'Expected Delivery',
                'Status',
                'Payment Status',
                'Payment Method',
                'Subtotal',
                'Total',
                'Amount Paid',
                'Balance',
                'Items Count',
                'Created By',
                'Created At'
            ]);

            // Data rows
            foreach ($purchaseOrders as $po) {
                fputcsv($file, [
                    $po->po_number,
                    $po->supplier->name ?? 'N/A',
                    $po->order_date,
                    $po->expected_delivery_date ?? '',
                    strtoupper($po->status),
                    strtoupper($po->payment_status ?? 'UNPAID'),
                    strtoupper(str_replace('_', ' ', $po->payment_method ?? 'CREDIT')),
                    number_format($po->subtotal, 2, '.', ''),
                    number_format($po->total_amount, 2, '.', ''),
                    number_format($po->amount_paid ?? 0, 2, '.', ''),
                    number_format($po->total_amount - ($po->amount_paid ?? 0), 2, '.', ''),
                    $po->items->count(),
                    $po->creator->name ?? 'N/A',
                    $po->created_at->format('Y-m-d H:i:s')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export purchase order as CSV
     * GET /api/purchase-orders/{purchaseOrder}/csv
     */
    public function exportCsv(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['items.product', 'supplier']);

        $filename = "PO-{$purchaseOrder->po_number}.csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($purchaseOrder) {
            $file = fopen('php://output', 'w');

            // Header
            fputcsv($file, ['Purchase Order: ' . $purchaseOrder->po_number]);
            fputcsv($file, ['Supplier: ' . $purchaseOrder->supplier->name]);
            fputcsv($file, ['Order Date: ' . $purchaseOrder->order_date->format('Y-m-d')]);
            fputcsv($file, ['Total Amount: ' . number_format($purchaseOrder->total_amount, 2)]);
            fputcsv($file, []);

            // Items header
            fputcsv($file, ['Product', 'SKU', 'Qty Ordered', 'Qty Received', 'Unit Cost', 'Discount %', 'Line Total']);

            // Items
            foreach ($purchaseOrder->items as $item) {
                fputcsv($file, [
                    $item->product->name,
                    $item->product->sku,
                    $item->quantity_ordered,
                    $item->quantity_received,
                    number_format($item->unit_cost, 2),
                    $item->discount_percent,
                    number_format($item->line_total, 2),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Delete purchase order (soft delete)
     * DELETE /api/purchase-orders/{purchaseOrder}
     */
    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('delete', $purchaseOrder);
        // Only draft POs can be deleted
        if ($purchaseOrder->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft purchase orders can be deleted',
            ], 422);
        }

        $purchaseOrder->delete();

        return response()->json([
            'message' => 'Purchase order deleted successfully',
        ]);
    }

    /**
     * Convert month/year format (YYYY-MM) to last day of month (YYYY-MM-DD)
     * Example: "2026-03" becomes "2026-03-31"
     * Full dates are passed through unchanged
     */
    private function convertToLastDayOfMonth($date)
    {
        if (empty($date)) {
            return $date;
        }

        // If already a full date (YYYY-MM-DD), return as is
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        // If month/year format (YYYY-MM), convert to last day of month
        if (preg_match('/^\d{4}-\d{2}$/', $date)) {
            try {
                $dateObj = \DateTime::createFromFormat('Y-m', $date);
                if ($dateObj) {
                    // Get last day of the month
                    return $dateObj->format('Y-m-t');
                }
            } catch (\Exception $e) {
                // If parsing fails, return original
                return $date;
            }
        }

        // Return original if format not recognized
        return $date;
    }
}