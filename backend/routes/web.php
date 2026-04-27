<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\PurchaseOrderController;

Route::get('/', function () {
    return view('welcome');
});

// Admin document routes (receipts, PDFs)
// Uses custom middleware that supports both session and token authentication
Route::prefix('admin')->middleware(['auth.token'])->group(function () {
    // Sale receipt printing (POS) - Standard format
    Route::get('/sales/{sale}/receipt', [SaleController::class, 'printReceipt'])
        ->name('sales.receipt');
    
    // Purchase Order PDF export
    Route::get('/purchase-orders/{purchaseOrder}/pdf', [PurchaseOrderController::class, 'exportPdf'])
        ->name('purchase-orders.pdf');
    
    // Thermal receipt (80mm POS printer optimized)
    Route::get('/receipts/{sale}', [SaleController::class, 'thermalReceipt'])
        ->name('receipts.thermal');
});
