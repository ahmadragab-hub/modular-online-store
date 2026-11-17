<?php

use App\Http\Controllers\Web\Storefront\AccountController;
use App\Http\Controllers\Web\Storefront\Auth\LoginCustomerController;
use App\Http\Controllers\Web\Storefront\Auth\LogoutCustomerController;
use App\Http\Controllers\Web\Storefront\Auth\RegisterCustomerController;
use App\Http\Controllers\Web\Storefront\CartController;
use App\Http\Controllers\Web\Storefront\CheckoutController;
use App\Http\Controllers\Web\Storefront\CustomerAddressController;
use App\Http\Controllers\Web\Storefront\HomeController;
use App\Http\Controllers\Web\Storefront\PreferenceController;
use App\Http\Controllers\Web\Storefront\ProductController;
use App\Http\Controllers\Web\Storefront\ProfileController;
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

Route::middleware(['auth', 'customer'])->prefix('account')->name('storefront.account.')->group(function (): void {
    Route::get('/', [AccountController::class, 'index'])->name('index');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/addresses', [CustomerAddressController::class, 'index'])->name('addresses.index');
    Route::get('/addresses/create', [CustomerAddressController::class, 'create'])->name('addresses.create');
    Route::post('/addresses', [CustomerAddressController::class, 'store'])->name('addresses.store');
    Route::get('/addresses/{address}/edit', [CustomerAddressController::class, 'edit'])->name('addresses.edit');
    Route::put('/addresses/{address}', [CustomerAddressController::class, 'update'])->name('addresses.update');
    Route::delete('/addresses/{address}', [CustomerAddressController::class, 'destroy'])->name('addresses.destroy');
    Route::post('/addresses/{address}/default-shipping', [CustomerAddressController::class, 'defaultShipping'])
        ->name('addresses.default-shipping');
    Route::post('/addresses/{address}/default-billing', [CustomerAddressController::class, 'defaultBilling'])
        ->name('addresses.default-billing');
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
