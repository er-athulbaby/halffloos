<?php

use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/stock');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Merchant listing screen. Approval is checked in the component's mount(),
    // where the store is resolved anyway -- one route does not warrant middleware.
    Route::get('/stock', fn () => view('merchant.stock'))->name('merchant.stock');
});
