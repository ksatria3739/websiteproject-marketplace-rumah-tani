<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CommerceController;
use App\Http\Controllers\Api\CurationController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\KycController;
use App\Http\Controllers\Api\LogisticsController;
use Illuminate\Support\Facades\Route;

// ==========================================
// 1. PUBLIC / GUEST ENDPOINTS (Read-Only)
// ==========================================
Route::get('/health', fn() => response()->json(['ok' => true, 'framework' => 'Laravel 12', 'database' => 'MySQL']));

// Authentication
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Public Catalog, Meta, and Articles
Route::prefix('public')->group(function () {
    Route::get('/catalog', [CatalogController::class, 'index']);
    Route::get('/catalog/{slug}', [CatalogController::class, 'show']);
    Route::get('/meta', [CatalogController::class, 'meta']);
    Route::get('/articles', [CatalogController::class, 'articles']);
    Route::get('/articles/{slug}', [CatalogController::class, 'articleDetail']);
});

// Public Logistics: Cost Estimator & Visual Shipment Tracking
Route::get('/logistics/estimate', [LogisticsController::class, 'estimate']);
Route::get('/logistics/track/{code}', [LogisticsController::class, 'track']);

// Payment Gateway Channels & Webhooks (Tripay, QRIS, Direct Gateway)
Route::get('/payment/channels', [CommerceController::class, 'paymentChannels']);
Route::post('/webhooks/payment', [CommerceController::class, 'webhook']);
Route::post('/webhooks/tripay', [CommerceController::class, 'tripayWebhook']);

// Public stream endpoint for uploaded KYC document files
Route::get('/kyc/documents/{id}/file', [KycController::class, 'viewDocumentFile']);

// ==========================================
// 2. AUTHENTICATED ENDPOINTS (Sanctum Token)
// ==========================================
Route::middleware('auth:sanctum')->group(function () {
    // Current user profile & session
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // KYC Document Upload (Petani, Tengkulak, Pembeli B2B)
    Route::post('/kyc/documents', [KycController::class, 'uploadDocuments']);

    // Region Creation (Input Asal Wilayah Lahan Baru)
    Route::post('/regions', [CatalogController::class, 'storeRegion']);

    // Orders (all authenticated roles can view their scoped orders)
    Route::get('/orders', [CommerceController::class, 'listOrders']);
    Route::get('/orders/{id}', [CommerceController::class, 'orderDetail']);

    // Mock Payment Sandbox settlement (simulates gateway callback for testing)
    Route::post('/webhooks/mock-settle', [CommerceController::class, 'mockSettle']);

    // ------------------------------------------
    // Pembeli (B2B / B2C)
    // ------------------------------------------
    Route::middleware('role:pembeli')->group(function () {
        Route::get('/cart', [CommerceController::class, 'getCart']);
        Route::put('/cart/items', [CommerceController::class, 'putCartItem']);
        Route::delete('/cart/items/{variantId}', [CommerceController::class, 'deleteCartItem']);
        Route::post('/checkout', [CommerceController::class, 'checkout']);
        Route::post('/orders/{id}/confirm-received', [CommerceController::class, 'confirmReceived']);
    });

    // ------------------------------------------
    // B2B Price Negotiation (Pembeli B2B, Petani, Tengkulak)
    // ------------------------------------------
    Route::middleware('role:pembeli,petani,admin_tengkulak')->group(function () {
        Route::post('/negotiations', [CommerceController::class, 'storeNegotiation']);
        Route::get('/negotiations', [CommerceController::class, 'listNegotiations']);
        Route::post('/negotiations/{id}/offers', [CommerceController::class, 'counterOffer']);
        Route::post('/negotiations/{id}/respond', [CommerceController::class, 'respondNegotiation']);
    });

    // ------------------------------------------
    // Petani & Tengkulak: Order Processing
    // ------------------------------------------
    Route::middleware('role:petani,admin_tengkulak')->group(function () {
        Route::post('/orders/{id}/accept', [CommerceController::class, 'acceptOrder']);
        Route::get('/dashboard/summary', [AdminController::class, 'dashboardSummary']);
    });

    // ------------------------------------------
    // Inventory: Petani, Tengkulak, Super Admin & Admin Support (L3 Operations)
    // ------------------------------------------
    Route::middleware('role:petani,admin_tengkulak,super_admin,admin')->group(function () {
        Route::get('/inventory/commodities', [InventoryController::class, 'index']);
        Route::post('/inventory/commodities', [InventoryController::class, 'store'])->middleware('kyc');
        Route::post('/inventory/upload-image', [InventoryController::class, 'uploadImage']);
        Route::post('/upload/commodity-image', [InventoryController::class, 'uploadImage']);
        Route::patch('/inventory/commodities/{id}', [InventoryController::class, 'update']);
        Route::patch('/inventory/variants/{id}', [InventoryController::class, 'updateVariant']);
        Route::post('/inventory/variants/{id}/stock', [InventoryController::class, 'adjustStock']);
    });

    // ------------------------------------------
    // Curation & Regional Logistics: Tengkulak, Super Admin & Admin Support
    // ------------------------------------------
    Route::middleware('role:admin_tengkulak,super_admin,admin')->group(function () {
        // Curation
        Route::get('/curation/queue', [CurationController::class, 'queue']);
        Route::post('/curation/{id}/decision', [CurationController::class, 'decision']);

        // KYC Verification Queue
        Route::get('/kyc/pending', [KycController::class, 'pendingQueue']);
        Route::post('/kyc/{userId}/review', [KycController::class, 'review']);

        // Regional Logistics & Fleet
        Route::get('/logistics/vehicles', [LogisticsController::class, 'listVehicles']);
        Route::post('/logistics/vehicles', [LogisticsController::class, 'storeVehicle']);
        Route::get('/logistics/routes', [LogisticsController::class, 'listRoutes']);
        Route::put('/logistics/routes', [LogisticsController::class, 'saveRoute']);
        Route::get('/logistics/shipments', [LogisticsController::class, 'listShipments']);
        Route::post('/logistics/shipments', [LogisticsController::class, 'allocateShipment']);
        Route::patch('/logistics/shipments/{id}/status', [LogisticsController::class, 'updateStatus']);
    });

    // ------------------------------------------
    // User Role Management & Operations: Super Admin & Admin Support (L3)
    // ------------------------------------------
    Route::middleware('role:super_admin,admin')->group(function () {
        Route::get('/admin/users', [AdminController::class, 'listUsers']);
        Route::patch('/admin/users/{id}', [AdminController::class, 'toggleUserStatus']);
        Route::patch('/admin/users/{id}/role', [AdminController::class, 'updateUserRole']);
        Route::get('/admin/analytics', [AdminController::class, 'analytics']);
        Route::get('/admin/transactions', [AdminController::class, 'transactions']);
    });

    // ------------------------------------------
    // Super Admin Exclusive: System Settings, Financial Export & Admin Creation
    // ------------------------------------------
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/admin/reports/export', [AdminController::class, 'exportReport']);
        Route::post('/admin/users/tengkulak', [AdminController::class, 'createTengkulak']);
        Route::get('/admin/settings', [AdminController::class, 'getSettings']);
        Route::put('/admin/settings/{key}', [AdminController::class, 'updateSetting']);
    });
});
