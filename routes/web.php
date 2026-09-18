<?php

use App\Http\Controllers\LoginController;
use App\Http\Controllers\RegisterController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'browse' : 'login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    // Customers self-register. Merchants never do -- see RegisterController.
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::view('/browse', 'customer.browse')->name('browse');

    // Approval is checked in each component's mount(), where the store is
    // resolved anyway -- two routes do not warrant a middleware class.
    Route::view('/stock', 'merchant.stock')->name('merchant.stock');
    Route::view('/till', 'merchant.till')->name('merchant.till');
});
