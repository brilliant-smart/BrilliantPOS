<?php

use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\InventoryAnalyticsController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\BatchTrackingController;
use App\Http\Controllers\Api\AutoReorderController;
use App\Http\Controllers\Api\AdvancedReportController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\SecurityController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\PriceHistoryController;
use App\Http\Controllers\Api\SupplierPriceComparisonController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\SettingController;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

// Login (Rate limited to prevent brute force)
Route::middleware('throttle:5,1')->post('/login', [AuthController::class, 'login']);

// Logout
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

// Forgot/Reset password (public, rate limited)
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/forgot-password', function (Request $request) {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink($request->only('email'));

        // Always return the same message to prevent email enumeration
        return response()->json([
            'message' => 'If an account with that email exists, a reset link has been sent.',
        ]);
    });

    Route::post('/reset-password', function (Request $request) {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        return response()->json([
            'message' => $status === Password::PASSWORD_RESET
                ? 'Password reset successfully'
                : 'Invalid or expired reset token',
        ], $status === Password::PASSWORD_RESET ? 200 : 422);
    });
});

// ============================================================
// AUTHENTICATED ROUTES
// ============================================================
Route::middleware(['auth:sanctum', 'throttle:120,1', 'ip.whitelist'])->group(function () {

    // ---- No role restriction (all authenticated users) ----
    Route::get('/me', function (Request $request) {
        return response()->json([
            'id'            => $request->user()->id,
            'name'          => $request->user()->name,
            'email'         => $request->user()->email,
            'role'          => $request->user()->role,
            'avatar_url'    => $request->user()->avatar_url,
        ]);
    });
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);

    // ========================================================
    // CASHIER-ACCESSIBLE ROUTES (owner, manager, cashier)
    // ========================================================
    Route::middleware('role:owner,manager,cashier')->group(function () {

        // Dashboard stats
        Route::get('/admin/dashboard-stats', [AdminDashboardController::class, 'stats']);

        // Product search (cashiers need this for POS)
        Route::get('/products', [ProductController::class, 'search']);
        Route::get('/products/barcode/search', [ProductController::class, 'searchByBarcode']);
        Route::get('/products/{product}', [ProductController::class, 'show']);

        // POS — core selling operations
        Route::prefix('pos')->group(function () {
            Route::post('/validate-stock', [\App\Http\Controllers\Api\POSController::class, 'validateStock']);
            Route::post('/complete-sale', [\App\Http\Controllers\Api\POSController::class, 'completeSale']);
            Route::post('/hold-cart', [\App\Http\Controllers\Api\POSController::class, 'holdCart']);
            Route::get('/held-carts', [\App\Http\Controllers\Api\POSController::class, 'getHeldCarts']);
            Route::get('/held-carts/{id}', [\App\Http\Controllers\Api\POSController::class, 'recallCart']);
            Route::delete('/held-carts/{id}', [\App\Http\Controllers\Api\POSController::class, 'deleteHeldCart']);
            Route::get('/reprint/{sale}', [\App\Http\Controllers\Api\POSController::class, 'getSaleForReprint']);
            Route::post('/sales/{sale}/receipt-token', [\App\Http\Controllers\Api\ReceiptController::class, 'generateToken']);
        });

        // Credit tracking — owner/manager only (registered BEFORE {sale} to avoid route conflicts)
        Route::middleware('role:owner,manager')->group(function () {
            Route::get('/sales/credit-summary', [SaleController::class, 'creditSummary']);
            Route::get('/sales/overdue-count', [SaleController::class, 'overdueCount']);
            Route::patch('/sales/{sale}/contact', [SaleController::class, 'updateContact']);
        });

        // Sales — view, create, pay (profit data hidden from cashiers by middleware)
        Route::middleware('hide.profit')->group(function () {
            Route::get('/sales', [SaleController::class, 'index']);
            Route::post('/sales', [SaleController::class, 'store']);
            Route::get('/sales/summary', [SaleController::class, 'summary']);
            Route::get('/sales/analytics', [SaleController::class, 'analytics']);
            Route::get('/sales/export', [SaleController::class, 'exportCsv']);
            Route::get('/sales/{sale}', [SaleController::class, 'show']);
            Route::post('/sales/{sale}/payment', [SaleController::class, 'recordPayment']);
        });

        // Alerts (low stock, expiry) — cashiers need to see these at POS
        Route::prefix('alerts')->group(function () {
            Route::get('/summary', [AlertController::class, 'summary']);
            Route::get('/low-stock', [AlertController::class, 'lowStock']);
            Route::get('/expiring-batches', [AlertController::class, 'expiringBatches']);
            Route::get('/expired-batches', [AlertController::class, 'expiredBatches']);
            Route::post('/mark-expired', [AlertController::class, 'markExpiredBatches']);
        });

        // Expenses — cashiers can record and view expenses
        Route::prefix('expenses')->group(function () {
            Route::get('/', [ExpenseController::class, 'index']);
            Route::post('/', [ExpenseController::class, 'store']);
            Route::get('/analytics', [ExpenseController::class, 'analytics']);
            Route::get('/{expense}', [ExpenseController::class, 'show']);
            Route::put('/{expense}', [ExpenseController::class, 'update']);
            Route::delete('/{expense}', [ExpenseController::class, 'destroy']);
        });

        // Expense categories — read access for cashiers
        Route::get('/expense-categories', [ExpenseCategoryController::class, 'index']);
        Route::get('/expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'show']);
    });

    // Expense categories — write access for owner/manager only
    Route::middleware('role:owner,manager')->group(function () {
        Route::post('/expense-categories', [ExpenseCategoryController::class, 'store']);
        Route::put('/expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'update']);
        Route::delete('/expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'destroy']);
    });

    // ========================================================
    // OWNER + MANAGER SUB-ROUTES (nested within cashier-accessible)
    // ========================================================

    // POS void — only owner and manager can void sales
    Route::middleware('role:owner,manager')->group(function () {
        Route::post('/pos/void-sale/{sale}', [\App\Http\Controllers\Api\POSController::class, 'voidSale']);
    });

    // Sales delete — owner only
    Route::middleware('role:owner')->group(function () {
        Route::delete('/sales/{sale}', [SaleController::class, 'destroy']);
    });

    // ========================================================
    // MANAGER-LEVEL ROUTES (owner + manager only)
    // ========================================================
    Route::middleware('role:owner,manager')->group(function () {

        // Products (profit data hidden from any future lower-role access)
        Route::middleware('hide.profit')->group(function () {
            Route::post('/products', [ProductController::class, 'store']);
            Route::get('/admin/products', [ProductController::class, 'adminIndex']);
            Route::put('/admin/products/{product}', [ProductController::class, 'update']);
            Route::post('/admin/products/{product}', [ProductController::class, 'update']); // Accept POST for FormData with _method
            Route::delete('/admin/products/{product}', [ProductController::class, 'destroy']);
        });

        // Suppliers
        Route::apiResource('suppliers', SupplierController::class);
        Route::post('/suppliers/{supplier}/toggle-status', [SupplierController::class, 'toggleStatus']);

        // Purchase Orders
        Route::post('/purchase-orders/price-comparison', [PurchaseOrderController::class, 'getPriceComparison']);
        Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
        Route::post('/purchase-orders', [PurchaseOrderController::class, 'store']);
        Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show']);
        Route::put('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update']);
        Route::delete('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy']);
        Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve']);
        Route::post('/purchase-orders/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject']);
        Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel']);
        Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receiveGoods']);
        Route::post('/purchase-orders/{purchaseOrder}/pdf-token', [PurchaseOrderController::class, 'generatePdfToken']);
        Route::post('/purchase-orders/{purchaseOrder}/record-payment', [PurchaseOrderController::class, 'recordPayment']);
        // PDF export moved to web routes (routes/web.php) for browser printing
        Route::get('/purchase-orders-export', [PurchaseOrderController::class, 'exportAll']);
        Route::get('/purchase-orders/{purchaseOrder}/csv', [PurchaseOrderController::class, 'exportCsv']);

        // Inventory Management
        Route::prefix('inventory')->group(function () {
            // Stock mutation endpoints — more restrictive rate limit
            Route::middleware('throttle:30,1')->group(function () {
                Route::post('/products/{product}/add-stock', [InventoryController::class, 'addStock']);
                Route::post('/products/{product}/reduce-stock', [InventoryController::class, 'reduceStock']);
                Route::post('/products/{product}/adjust-stock', [InventoryController::class, 'adjustStock']);
                Route::post('/bulk-update', [InventoryController::class, 'bulkUpdateStock']);
            });

            Route::get('/products/{product}/stock-history', [InventoryController::class, 'getStockHistory']);
            Route::get('/low-stock', [InventoryController::class, 'getLowStockProducts']);
            Route::get('/out-of-stock', [InventoryController::class, 'getOutOfStockProducts']);
            Route::get('/summary', [InventoryController::class, 'getInventorySummary']);

            // Inventory Analytics & Reports
            Route::prefix('analytics')->group(function () {
                Route::get('/dashboard', [InventoryAnalyticsController::class, 'dashboard']);
                Route::get('/movements', [InventoryAnalyticsController::class, 'movements']);
                Route::get('/turnover', [InventoryAnalyticsController::class, 'turnover']);
                Route::get('/export', [InventoryAnalyticsController::class, 'export']);
            });
        });

        // Batch Tracking
        Route::prefix('batches')->group(function () {
            Route::get('/', [BatchTrackingController::class, 'index']);
            Route::post('/', [BatchTrackingController::class, 'store']);
            Route::get('/expiring', [BatchTrackingController::class, 'expiring']);
            Route::get('/expired', [BatchTrackingController::class, 'expired']);
            Route::get('/inventory-report', [BatchTrackingController::class, 'inventoryReport']);
            Route::get('/{batch}', [BatchTrackingController::class, 'show']);
            Route::put('/{batch}', [BatchTrackingController::class, 'update']);
            Route::post('/{batch}/mark-expired', [BatchTrackingController::class, 'markExpired']);
        });

        // Auto Reorder
        Route::prefix('auto-reorder')->group(function () {
            Route::get('/suggestions', [AutoReorderController::class, 'suggestions']);
            Route::post('/trigger-check', [AutoReorderController::class, 'triggerCheck']);
            Route::get('/logs', [AutoReorderController::class, 'logs']);
            Route::get('/statistics', [AutoReorderController::class, 'statistics']);
        });

        // Financial Reports
        Route::prefix('reports')->group(function () {
            Route::get('/financial-overview', [ReportController::class, 'financialOverview']);
            Route::get('/cashier-performance', [ReportController::class, 'cashierPerformance']);
            Route::get('/top-selling-products', [ReportController::class, 'topSellingProducts']);
            Route::get('/stock-variances', [ReportController::class, 'stockVariances']);
            Route::get('/reorder', [ReportController::class, 'reorderReport']);
            Route::get('/expiring-products', [ReportController::class, 'expiringProducts']);
        });

        // Advanced Reports
        Route::prefix('advanced-reports')->group(function () {
            Route::get('/inventory-aging', [AdvancedReportController::class, 'inventoryAging']);
            Route::get('/supplier-performance', [AdvancedReportController::class, 'supplierPerformance']);
            Route::get('/abc-analysis', [AdvancedReportController::class, 'abcAnalysis']);
            Route::get('/dead-stock', [AdvancedReportController::class, 'deadStock']);
            Route::get('/stockout', [AdvancedReportController::class, 'stockout']);
            Route::get('/sales-forecast', [AdvancedReportController::class, 'salesForecast']);
        });

        // Notifications
        Route::prefix('notifications')->group(function () {
            Route::get('/settings', [NotificationController::class, 'getSettings']);
            Route::put('/settings/{key}', [NotificationController::class, 'updateSetting']);
            Route::get('/logs', [NotificationController::class, 'getLogs']);
            Route::post('/test', [NotificationController::class, 'testNotification']);
        });

        // Backup & Restore (list, create, download, delete — upload and restore are owner-only)
        Route::prefix('backups')->group(function () {
            Route::get('/', [BackupController::class, 'index']);
            Route::post('/create', [BackupController::class, 'create']);
            Route::get('/{filename}/download', [BackupController::class, 'download']);
            Route::delete('/{filename}', [BackupController::class, 'destroy']);
        });
    });

    // ========================================================
    // OWNER-ONLY ROUTES
    // ========================================================
    Route::middleware('role:owner')->group(function () {

        // User management
        Route::get('/admin/users', [UserController::class, 'index']);
        Route::post('/admin/users', [UserController::class, 'store']);
        Route::patch('/admin/users/{user}', [UserController::class, 'update']);
        Route::delete('/admin/users/{user}', [UserController::class, 'destroy']);
        Route::delete('/admin/users/{user}/force', [UserController::class, 'forceDelete']);

        // Price History
        Route::get('/price-history', [PriceHistoryController::class, 'index']);
        Route::get('/products/{productId}/price-history', [PriceHistoryController::class, 'productHistory']);

        // Supplier Price Comparison
        Route::get('/reports/supplier-price-comparison', [SupplierPriceComparisonController::class, 'index']);
        Route::get('/reports/supplier-performance', [SupplierPriceComparisonController::class, 'supplierPerformance']);
        Route::get('/reports/best-supplier/{productId}', [SupplierPriceComparisonController::class, 'bestSupplier']);

        // Audit Logs
        Route::prefix('audit-logs')->group(function () {
            Route::get('/', [AuditLogController::class, 'index']);
            Route::get('/statistics', [AuditLogController::class, 'statistics']);
            Route::get('/{auditLog}', [AuditLogController::class, 'show']);
        });

        // Settings
        Route::prefix('settings')->group(function () {
            Route::get('/', [SettingController::class, 'index']);
            Route::put('/', [SettingController::class, 'update']);
        });

        // Security
        Route::prefix('security')->group(function () {
            Route::get('/settings', [SecurityController::class, 'getSettings']);
            Route::put('/settings', [SecurityController::class, 'updateSettings']);
            Route::get('/ip-whitelist', [SecurityController::class, 'getWhitelist']);
            Route::post('/ip-whitelist', [SecurityController::class, 'addToWhitelist']);
            Route::delete('/ip-whitelist/{id}', [SecurityController::class, 'removeFromWhitelist']);
            Route::get('/login-attempts', [SecurityController::class, 'getLoginAttempts']);
            Route::post('/2fa/enable', [SecurityController::class, 'enable2FA']);
            Route::post('/2fa/confirm', [SecurityController::class, 'confirm2FA']);
            Route::post('/2fa/disable', [SecurityController::class, 'disable2FA']);
        });

        // Webhooks & Integrations
        Route::prefix('webhooks')->group(function () {
            Route::get('/', [WebhookController::class, 'getWebhooks']);
            Route::post('/', [WebhookController::class, 'createWebhook']);
            Route::delete('/{id}', [WebhookController::class, 'deleteWebhook']);
        });

        // Backup upload & restore (destructive operations)
        Route::post('/backups/upload', [BackupController::class, 'upload']);
        Route::post('/backups/{filename}/restore', [BackupController::class, 'restore']);
    });
});