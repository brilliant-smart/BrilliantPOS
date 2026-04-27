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
use Illuminate\Http\Request;

//Login (Rate limited to prevent brute force)
Route::middleware('throttle:20,1')->post('/login', [AuthController::class, 'login']);
//Logout
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

/* Products — barcode search only (used by POS) */
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/products/barcode/search', [ProductController::class, 'searchByBarcode']);
});

//Forget Password
Route::post('/forgot-password', function (Request $request) {
    $request->validate(['email' => 'required|email']);

    $status = Password::sendResetLink(
        $request->only('email')
    );

    return response()->json([
        'message' => __($status),
    ]);
});

//Reset password
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
                'password' => bcrypt($password),
            ])->save();
        }
    );

    return response()->json([
        'message' => __($status),
    ]);
});

/* Protected */
Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    /* Authenticated user */
    Route::get('/me', function (Request $request) {
        return response()->json([
            'id'            => $request->user()->id,
            'name'          => $request->user()->name,
            'email'         => $request->user()->email,
            'role'          => $request->user()->role,
            'avatar_url'    => $request->user()->avatar_url,
        ]);
    });

    /* User Profile */
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);

    /* Products (Admin) - Hide cost data from cashiers */
    Route::middleware('hide.profit')->group(function () {
        Route::post('/products', [ProductController::class, 'store']);
        Route::get('/products', [ProductController::class, 'adminIndex']);
        Route::get('/admin/products', [ProductController::class, 'adminIndex']);
        Route::put('/admin/products/{product}', [ProductController::class, 'update']);
        Route::post('/admin/products/{product}', [ProductController::class, 'update']); // Accept POST for FormData with _method
        Route::delete('/admin/products/{product}', [ProductController::class, 'destroy']);
    });

    /* Inventory Management */
    Route::prefix('inventory')->group(function () {
        // Stock mutation endpoints - more restrictive rate limit
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

        /* Inventory Analytics & Reports */
        Route::prefix('analytics')->group(function () {
            Route::get('/dashboard', [InventoryAnalyticsController::class, 'dashboard']);
            Route::get('/movements', [InventoryAnalyticsController::class, 'movements']);
            Route::get('/turnover', [InventoryAnalyticsController::class, 'turnover']);
            Route::get('/export', [InventoryAnalyticsController::class, 'export']);
        });
    });

    /* Admin dashboard */
    Route::get(
        '/admin/dashboard-stats',
        [AdminDashboardController::class, 'stats']
    );

    /* Admin users */
    Route::get('/admin/users', [UserController::class, 'index']);
    Route::post('/admin/users', [UserController::class, 'store']);
    Route::patch('/admin/users/{user}', [UserController::class, 'update']);
    Route::delete('/admin/users/{user}', [UserController::class, 'destroy']);
    Route::delete('/admin/users/{user}/force', [UserController::class, 'forceDelete']);

    /* Suppliers */
    Route::apiResource('suppliers', SupplierController::class);

    /* Expenses */
    Route::prefix('expenses')->group(function () {
        Route::get('/', [ExpenseController::class, 'index']);
        Route::post('/', [ExpenseController::class, 'store']);
        Route::get('/analytics', [ExpenseController::class, 'analytics']);
        Route::get('/{expense}', [ExpenseController::class, 'show']);
        Route::put('/{expense}', [ExpenseController::class, 'update']);
        Route::delete('/{expense}', [ExpenseController::class, 'destroy']);
    });

    /* Expense Categories */
    Route::apiResource('expense-categories', ExpenseCategoryController::class);
    Route::post('/suppliers/{supplier}/toggle-status', [SupplierController::class, 'toggleStatus']);

    /* Price History */
    Route::get('/price-history', [PriceHistoryController::class, 'index']);
    Route::get('/products/{productId}/price-history', [PriceHistoryController::class, 'productHistory']);

    /* Supplier Price Comparison */
    Route::get('/reports/supplier-price-comparison', [SupplierPriceComparisonController::class, 'index']);
    Route::get('/reports/supplier-performance', [SupplierPriceComparisonController::class, 'supplierPerformance']);
    Route::get('/reports/best-supplier/{productId}', [SupplierPriceComparisonController::class, 'bestSupplier']);

    /* Purchase Orders */
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
    Route::post('/purchase-orders/{purchaseOrder}/record-payment', [PurchaseOrderController::class, 'recordPayment']);
    // PDF export moved to web routes (routes/web.php) for browser printing
    Route::get('/purchase-orders-export', [PurchaseOrderController::class, 'exportAll']);
    Route::get('/purchase-orders/{purchaseOrder}/csv', [PurchaseOrderController::class, 'exportCsv']);

    /* Sales - Hide profit data from cashiers */
    Route::middleware('hide.profit')->group(function () {
        Route::get('/sales', [SaleController::class, 'index']);
        Route::post('/sales', [SaleController::class, 'store']);
        Route::get('/sales/summary', [SaleController::class, 'summary']);
        Route::get('/sales/analytics', [SaleController::class, 'analytics']);
        Route::get('/sales/export', [SaleController::class, 'exportCsv']);
        Route::get('/sales/{sale}', [SaleController::class, 'show']);
        // Receipt printing moved to web routes (routes/web.php) for browser printing
        Route::post('/sales/{sale}/payment', [SaleController::class, 'recordPayment']);
        Route::delete('/sales/{sale}', [SaleController::class, 'destroy']);
    });

    /* Financial Reports */
    Route::prefix('reports')->group(function () {
        Route::get('/financial-overview', [ReportController::class, 'financialOverview']);
        Route::get('/cashier-performance', [ReportController::class, 'cashierPerformance']);
        Route::get('/top-selling-products', [ReportController::class, 'topSellingProducts']);
        Route::get('/stock-variances', [ReportController::class, 'stockVariances']);
        Route::get('/reorder', [ReportController::class, 'reorderReport']);
        Route::get('/expiring-products', [ReportController::class, 'expiringProducts']);
    });

    /* Batch Tracking */
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

    /* Auto Reorder */
    Route::prefix('auto-reorder')->group(function () {
        Route::get('/suggestions', [AutoReorderController::class, 'suggestions']);
        Route::post('/trigger-check', [AutoReorderController::class, 'triggerCheck']);
        Route::get('/logs', [AutoReorderController::class, 'logs']);
        Route::get('/statistics', [AutoReorderController::class, 'statistics']);
    });

    /* Advanced Reports */
    Route::prefix('advanced-reports')->group(function () {
        Route::get('/inventory-aging', [AdvancedReportController::class, 'inventoryAging']);
        Route::get('/supplier-performance', [AdvancedReportController::class, 'supplierPerformance']);
        Route::get('/abc-analysis', [AdvancedReportController::class, 'abcAnalysis']);
        Route::get('/dead-stock', [AdvancedReportController::class, 'deadStock']);
        Route::get('/stockout', [AdvancedReportController::class, 'stockout']);
        Route::get('/sales-forecast', [AdvancedReportController::class, 'salesForecast']);
    });

    /* Notifications */
    Route::prefix('notifications')->group(function () {
        Route::get('/settings', [NotificationController::class, 'getSettings']);
        Route::put('/settings/{key}', [NotificationController::class, 'updateSetting']);
        Route::get('/logs', [NotificationController::class, 'getLogs']);
        Route::post('/test', [NotificationController::class, 'testNotification']);
    });

    /* Audit Logs */
    Route::prefix('audit-logs')->group(function () {
        Route::get('/', [AuditLogController::class, 'index']);
        Route::get('/statistics', [AuditLogController::class, 'statistics']);
        Route::get('/{auditLog}', [AuditLogController::class, 'show']);
    });

    // Backup & Restore
    Route::prefix('backups')->group(function () {
        // All authenticated users can list, create, download, and delete backups
        Route::get('/', [BackupController::class, 'index']);
        Route::post('/create', [BackupController::class, 'create']);
        Route::get('/{filename}/download', [BackupController::class, 'download']);
        Route::delete('/{filename}', [BackupController::class, 'destroy']);

        // Upload and Restore - Owner only (checked in controller)
        Route::post('/upload', [BackupController::class, 'upload']);
        Route::post('/{filename}/restore', [BackupController::class, 'restore']);
    });

    // Alerts (Low Stock, Expiry)
    Route::prefix('alerts')->group(function () {
        Route::get('/summary', [AlertController::class, 'summary']);
        Route::get('/low-stock', [AlertController::class, 'lowStock']);
        Route::get('/expiring-batches', [AlertController::class, 'expiringBatches']);
        Route::get('/expired-batches', [AlertController::class, 'expiredBatches']);
    });

    // POS System
    Route::prefix('pos')->group(function () {
        Route::post('/validate-stock', [\App\Http\Controllers\Api\POSController::class, 'validateStock']);
        Route::post('/complete-sale', [\App\Http\Controllers\Api\POSController::class, 'completeSale']);

        // Hold & Recall
        Route::post('/hold-cart', [\App\Http\Controllers\Api\POSController::class, 'holdCart']);
        Route::get('/held-carts', [\App\Http\Controllers\Api\POSController::class, 'getHeldCarts']);
        Route::get('/held-carts/{id}', [\App\Http\Controllers\Api\POSController::class, 'recallCart']);
        Route::delete('/held-carts/{id}', [\App\Http\Controllers\Api\POSController::class, 'deleteHeldCart']);

        // Void & Reprint
        Route::post('/void-sale/{sale}', [\App\Http\Controllers\Api\POSController::class, 'voidSale']);
        Route::get('/reprint/{sale}', [\App\Http\Controllers\Api\POSController::class, 'getSaleForReprint']);
    });

    /* Security (Owner only) */
    Route::prefix('security')->group(function () {
        Route::get('/settings', [SecurityController::class, 'getSettings']);
        Route::put('/settings', [SecurityController::class, 'updateSettings']);
        Route::get('/ip-whitelist', [SecurityController::class, 'getWhitelist']);
        Route::post('/ip-whitelist', [SecurityController::class, 'addToWhitelist']);
        Route::delete('/ip-whitelist/{id}', [SecurityController::class, 'removeFromWhitelist']);
        Route::get('/login-attempts', [SecurityController::class, 'getLoginAttempts']);
        Route::post('/2fa/enable', [SecurityController::class, 'enable2FA']);
        Route::post('/2fa/disable', [SecurityController::class, 'disable2FA']);
    });

    /* Webhooks & Integrations (Owner only) */
    Route::prefix('webhooks')->group(function () {
        Route::get('/', [WebhookController::class, 'getWebhooks']);
        Route::post('/', [WebhookController::class, 'createWebhook']);
        Route::delete('/{id}', [WebhookController::class, 'deleteWebhook']);
    });

    /* Settings (Owner only) */
    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingController::class, 'index']);
        Route::put('/', [SettingController::class, 'update']);
    });
});