<?php

use App\Http\Controllers\Web\Storefront\CheckoutController;
use App\Http\Controllers\Web\Storefront\HomeController;
use App\Http\Controllers\Web\Storefront\PreferenceController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('storefront.home');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('storefront.checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('storefront.checkout.store');
Route::get('/checkout/success/{publicUuid}', [CheckoutController::class, 'success'])
    ->name('storefront.checkout.success');

Route::post('/preferences/locale', [PreferenceController::class, 'locale'])->name('storefront.locale');
Route::post('/preferences/theme', [PreferenceController::class, 'theme'])->name('storefront.theme');
