<?php

use App\Http\Controllers\Web\Admin\AdminPreferenceController;
use App\Http\Controllers\Web\Admin\Auth\LoginAdminController;
use App\Http\Controllers\Web\Admin\Auth\LogoutAdminController;
use App\Http\Controllers\Web\Admin\Catalog\CategoryController;
use App\Http\Controllers\Web\Admin\Catalog\ColorController;
use App\Http\Controllers\Web\Admin\Catalog\ProductController;
use App\Http\Controllers\Web\Admin\Catalog\ProductImageController;
use App\Http\Controllers\Web\Admin\Catalog\ProductVariantController;
use App\Http\Controllers\Web\Admin\Catalog\SizeController;
use App\Http\Controllers\Web\Admin\Customers\CustomerController;
use App\Http\Controllers\Web\Admin\DashboardController;
use App\Http\Controllers\Web\Admin\Inventory\InventoryController;
use App\Http\Controllers\Web\Admin\Orders\OrderController;
use App\Http\Controllers\Web\Admin\Store\ExchangeRateController;
use App\Http\Controllers\Web\Admin\Store\StoreSettingsController;
use App\Http\Middleware\ApplyAdminPreferences;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(ApplyAdminPreferences::class)
    ->group(function (): void {
        Route::middleware('guest')->group(function (): void {
            Route::get('/login', [LoginAdminController::class, 'create'])->name('login.create');
            Route::post('/login', [LoginAdminController::class, 'store'])
                ->middleware('throttle:5,1')
                ->name('login.store');
        });

        Route::middleware(['auth', 'admin'])->group(function (): void {
            Route::post('/logout', [LogoutAdminController::class, 'destroy'])->name('logout.destroy');
            Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

            Route::post('/preferences/locale', [AdminPreferenceController::class, 'locale'])->name('locale');
            Route::post('/preferences/theme', [AdminPreferenceController::class, 'theme'])->name('theme');

            Route::resource('categories', CategoryController::class)->except(['show', 'destroy']);
            Route::resource('products', ProductController::class)->except(['destroy']);
            Route::post('products/{product}/publish', [ProductController::class, 'publish'])->name('products.publish');
            Route::post('products/{product}/archive', [ProductController::class, 'archive'])->name('products.archive');
            Route::post('products/{product}/translations', [ProductController::class, 'updateTranslations'])
                ->name('products.translations.update');
            Route::resource('products.variants', ProductVariantController::class)->except(['show', 'destroy']);
            Route::post('products/{product}/images', [ProductImageController::class, 'store'])
                ->name('products.images.store');
            Route::delete('products/{product}/images/{image}', [ProductImageController::class, 'destroy'])
                ->name('products.images.destroy');
            Route::resource('sizes', SizeController::class)->only(['index', 'store']);
            Route::resource('colors', ColorController::class)->only(['index', 'store']);

            Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
            Route::get('inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
            Route::post('inventory/{variant}/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');

            Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
            Route::post('orders/{order}/transition', [OrderController::class, 'transition'])->name('orders.transition');
            Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

            Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
            Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

            Route::get('settings', [StoreSettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [StoreSettingsController::class, 'update'])->name('settings.update');
            Route::get('exchange-rates', [ExchangeRateController::class, 'index'])->name('exchange-rates.index');
            Route::post('exchange-rates', [ExchangeRateController::class, 'store'])->name('exchange-rates.store');
        });
    });
