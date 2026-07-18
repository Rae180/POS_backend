<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\PurchaseCartController;
use App\Http\Controllers\Inventory\PurchaseController;
use App\Http\Controllers\Management\CustomerController;
use App\Http\Controllers\Management\SupplierController;
use App\Http\Controllers\Pos\CartController;
use App\Http\Controllers\Pos\OrderController;
use App\Http\Controllers\Pos\ShiftController;
use App\Http\Controllers\Settings\SettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    /*
    |--------------------------------------------------------------------------
    | Admin + Cashier: POS floor operations
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin|cashier')->group(function () {
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{product}', [ProductController::class, 'show']);

        Route::apiResource('customers', CustomerController::class);

        Route::get('/orders', [OrderController::class, 'apiIndex']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::post('/orders/partial-payment', [OrderController::class, 'partialPayment']);
        Route::get('/orders/{order}/receipt', [OrderController::class, 'receipt']);

        Route::prefix('cart')->group(function () {
            Route::get('/', [CartController::class, 'index']);
            Route::post('/', [CartController::class, 'store']);
            Route::patch('/quantity', [CartController::class, 'changeQty']);
            Route::delete('/item', [CartController::class, 'delete']);
            Route::delete('/', [CartController::class, 'empty']);
        });

        Route::get('/shift/current', [ShiftController::class, 'current']);
        Route::post('/shift/open', [ShiftController::class, 'open']);
        Route::post('/shift/close', [ShiftController::class, 'close']);
    });

    /*
    |--------------------------------------------------------------------------
    | Admin only: inventory, purchasing, settings, money-reversing actions
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin')->group(function () {
        Route::apiResource('products', ProductController::class)->except(['index', 'show']);
        Route::apiResource('suppliers', SupplierController::class);

        Route::delete('/orders/{order}', [OrderController::class, 'destroy']);
        Route::post('/orders/{order}/refund', [OrderController::class, 'refund']);

        Route::apiResource('purchases', PurchaseController::class);
        Route::get('/purchases/data', [PurchaseController::class, 'data']);
        Route::get('/purchases/{purchase}/receipt', [PurchaseController::class, 'receipt']);

        Route::prefix('purchase-cart')->group(function () {
            Route::get('/', [PurchaseCartController::class, 'index']);
            Route::post('/', [PurchaseCartController::class, 'store']);
            Route::patch('/quantity', [PurchaseCartController::class, 'changeQty']);
            Route::patch('/price', [PurchaseCartController::class, 'changePrice']);
            Route::delete('/item', [PurchaseCartController::class, 'delete']);
            Route::delete('/', [PurchaseCartController::class, 'empty']);
        });

        Route::get('/shifts', [ShiftController::class, 'index']);

        Route::get('/settings', [SettingController::class, 'index']);
        Route::put('/settings', [SettingController::class, 'store']);
    });
});