<?php

use App\Http\Controllers\Admin\HomeController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\PurchaseCartController;
use App\Http\Controllers\Inventory\PurchaseController;
use App\Http\Controllers\Management\CustomerController;
use App\Http\Controllers\Management\SupplierController;
use App\Http\Controllers\Pos\CartController;
use App\Http\Controllers\Pos\OrderController;
use App\Http\Controllers\Settings\SettingController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Pos\ShiftController;

Route::get('/', fn(): Redirector|RedirectResponse => redirect('/admin'));

Auth::routes();

Route::prefix('admin')->middleware(['auth', 'locale'])->group(function (): void {

    // ── Available to any authenticated user, regardless of role ──
    Route::get('/', HomeController::class)->name('home');

    Route::get('/locale/{type}', function ($type) {
        $translations = trans($type);
        return response()->json($translations);
    });

    Route::get('/lang-switch/{lang}', function ($lang) {
        $supportedLocales = ['en', 'es'];

        if (in_array($lang, $supportedLocales)) {
            session(['locale' => $lang]);
            app()->setLocale($lang);
        }

        return redirect()->back();
    })->name('lang.switch');


    // ── Admin + Cashier: POS floor operations ──
    Route::middleware('role:admin|cashier')->group(function (): void {
        // Product lookup only — no create/edit/delete
        Route::resource('products', ProductController::class)->only(['index', 'show']);

        Route::resource('customers', CustomerController::class);

        // Full order lifecycle except destroy and refund (admin-only, below)
        Route::resource('orders', OrderController::class)->except(['destroy']);

        // POS Cart
        Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
        Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
        Route::post('/cart/change-qty', [CartController::class, 'changeQty']);
        Route::delete('/cart/delete', [CartController::class, 'delete']);
        Route::delete('/cart/empty', [CartController::class, 'empty']);

        Route::post('/orders/partial-payment', [OrderController::class, 'partialPayment'])->name('orders.partial-payment');
        Route::get('/orders/{order}/receipt', [OrderController::class, 'receipt'])->name('orders.receipt');
        Route::get('/shift/current', [ShiftController::class, 'current'])->name('shift.current');
        Route::post('/shift/open', [ShiftController::class, 'open'])->name('shift.open');
        Route::post('/shift/close', [ShiftController::class, 'close'])->name('shift.close');
    });


    // ── Admin only: inventory, purchasing, settings, money-reversing actions ──
    Route::middleware('role:admin')->group(function (): void {
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingController::class, 'store'])->name('settings.store');

        // Create/edit/delete slice of products (index/show live in the shared group above)
        Route::resource('products', ProductController::class)->except(['index', 'show']);

        Route::resource('suppliers', SupplierController::class);

        Route::delete('orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
        Route::post('/orders/{order}/refund', [OrderController::class, 'refund'])->name('orders.refund');

        Route::get('/purchases/data', [PurchaseController::class, 'data'])->name('purchases.data');
        Route::get('/purchases/{purchase}/receipt', [PurchaseController::class, 'receipt'])->name('purchases.receipt');
        Route::resource('purchases', PurchaseController::class);
        Route::get('/shifts', [ShiftController::class, 'index'])->name('shifts.index');

        Route::prefix('purchase-cart')->name('purchase-cart.')->group(function (): void {
            Route::get('/', [PurchaseCartController::class, 'index'])->name('index');
            Route::post('/', [PurchaseCartController::class, 'store'])->name('store');
            Route::post('/change-qty', [PurchaseCartController::class, 'changeQty'])->name('change-qty');
            Route::post('/change-price', [PurchaseCartController::class, 'changePrice'])->name('change-price');
            Route::delete('/delete', [PurchaseCartController::class, 'delete'])->name('delete');
            Route::delete('/empty', [PurchaseCartController::class, 'empty'])->name('empty');
        });
    });
});