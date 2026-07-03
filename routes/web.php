<?php

use App\Http\Controllers\Dt\ForgotPasswordController;
use App\Http\Controllers\Dt\LoginController;
use App\Http\Controllers\Dt\PasswordChangeController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

// DT panel routes
Route::prefix('dt')->name('dt.')->group(function () {
    // Guest routes (unauthenticated DT users)
    Route::middleware('guest:dt')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store']);

        Route::get('forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
        Route::post('forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
    });

    // Authenticated DT routes
    Route::middleware(['auth:dt'])->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

        // Password change (available even when password is expired)
        Route::get('password/change', [PasswordChangeController::class, 'create'])->name('password.change');
        Route::post('password/change', [PasswordChangeController::class, 'store'])->name('password.change.update');

        // All other DT routes require an active (non-expired) password
        Route::middleware('password_expires')->group(function () {
            Route::inertia('dashboard', 'dt/dashboard')->name('dashboard');
        });
    });
});

require __DIR__.'/settings.php';
