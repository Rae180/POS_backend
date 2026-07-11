<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\PurchaseCartController;
use App\Http\Controllers\Inventory\PurchaseController;
use App\Http\Controllers\Management\CustomerController;
use App\Http\Controllers\Management\SupplierController;
use App\Http\Controllers\Pos\CartController;
use App\Http\Controllers\Pos\OrderController;
use App\Http\Controllers\Settings\SettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::post('/login', [A::class, 'login']);

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    Route::post('/logout', [LoginController::class, 'logout']);
    Route::get('/me', [LoginController::class, 'me']);

    /*
    |--------------------------------------------------------------------------
    | Products
    |--------------------------------------------------------------------------
    */

    Route::apiResource('products', ProductController::class);

    /*
    |--------------------------------------------------------------------------
    | Customers
    |--------------------------------------------------------------------------
    */

    Route::apiResource('customers', CustomerController::class);

    /*
    |--------------------------------------------------------------------------
    | Suppliers
    |--------------------------------------------------------------------------
    */

    Route::apiResource('suppliers', SupplierController::class);

    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    */

    Route::apiResource('orders', OrderController::class);

    Route::post(
        '/orders/partial-payment',
        [OrderController::class, 'partialPayment']
    );

    /*
    |--------------------------------------------------------------------------
    | Purchases
    |--------------------------------------------------------------------------
    */

    Route::apiResource('purchases', PurchaseController::class);

    Route::get(
        '/purchases/data',
        [PurchaseController::class, 'data']
    );

    Route::get(
        '/purchases/{purchase}/receipt',
        [PurchaseController::class, 'receipt']
    );

    /*
    |--------------------------------------------------------------------------
    | POS Cart
    |--------------------------------------------------------------------------
    */

    Route::prefix('cart')->group(function () {

        Route::get('/', [CartController::class, 'index']);

        Route::post('/', [CartController::class, 'store']);

        Route::patch('/quantity', [CartController::class, 'changeQty']);

        Route::delete('/item', [CartController::class, 'delete']);

        Route::delete('/', [CartController::class, 'empty']);
    });

    /*
    |--------------------------------------------------------------------------
    | Purchase Cart
    |--------------------------------------------------------------------------
    */

    Route::prefix('purchase-cart')->group(function () {

        Route::get('/', [PurchaseCartController::class, 'index']);

        Route::post('/', [PurchaseCartController::class, 'store']);

        Route::patch('/quantity', [PurchaseCartController::class, 'changeQty']);

        Route::patch('/price', [PurchaseCartController::class, 'changePrice']);

        Route::delete('/item', [PurchaseCartController::class, 'delete']);

        Route::delete('/', [PurchaseCartController::class, 'empty']);
    });

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    */

    Route::get('/settings', [SettingController::class, 'index']);

    Route::put('/settings', [SettingController::class, 'store']);
});