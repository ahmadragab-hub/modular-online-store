<?php

use App\Http\Controllers\Web\Storefront\HomeController;
use App\Http\Controllers\Web\Storefront\PreferenceController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('storefront.home');

Route::post('/preferences/locale', [PreferenceController::class, 'locale'])->name('storefront.locale');
Route::post('/preferences/theme', [PreferenceController::class, 'theme'])->name('storefront.theme');
