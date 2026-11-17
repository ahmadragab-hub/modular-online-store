<?php

use App\Http\Controllers\Web\Storefront\AccountController;
use App\Http\Controllers\Web\Storefront\Auth\LoginCustomerController;
use App\Http\Controllers\Web\Storefront\Auth\LogoutCustomerController;
use App\Http\Controllers\Web\Storefront\Auth\RegisterCustomerController;
use App\Http\Controllers\Web\Storefront\CartController;
use App\Http\Controllers\Web\Storefront\CheckoutController;
use App\Http\Controllers\Web\Storefront\HomeController;
use App\Http\Controllers\Web\Storefront\PreferenceController;
use App\Http\Controllers\Web\Storefront\ProductController;
use App\Http\Controllers\Web\Storefront\ShopController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisterCustomerController::class, 'create'])->name('storefront.register.create');
    Route::post('/register', [RegisterCustomerController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('storefront.register.store');
    Route::get('/login', [LoginCustomerController::class, 'create'])->name('storefront.login.create');
    Route::post('/login', [LoginCustomerController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('storefront.login.store');
});

Route::post('/logout', [LogoutCustomerController::class, 'destroy'])
    ->middleware('auth')
    ->name('storefront.logout.destroy');

Route::middleware(['auth', 'customer'])->group(function (): void {
    Route::get('/account', [AccountController::class, 'index'])->name('storefront.account.index');
});

Route::get('/', [HomeController::class, 'index'])->name('storefront.home');

Route::get('/shop', [ShopController::class, 'index'])->name('storefront.shop.index');
Route::get('/products/{slug}', [ProductController::class, 'show'])->name('storefront.products.show');

Route::get('/cart', [CartController::class, 'index'])->name('storefront.cart.show');
Route::post('/cart/items', [CartController::class, 'store'])->name('storefront.cart.items.store');
Route::patch('/cart/items/{cartItem}', [CartController::class, 'update'])->name('storefront.cart.items.update');
Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy'])->name('storefront.cart.items.destroy');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('storefront.checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('storefront.checkout.store');
Route::get('/checkout/success/{publicUuid}', [CheckoutController::class, 'success'])
    ->name('storefront.checkout.success');

Route::post('/preferences/locale', [PreferenceController::class, 'locale'])->name('storefront.locale');
Route::post('/preferences/theme', [PreferenceController::class, 'theme'])->name('storefront.theme');
Route::post('/preferences/currency', [PreferenceController::class, 'displayCurrency'])->name('storefront.currency');
