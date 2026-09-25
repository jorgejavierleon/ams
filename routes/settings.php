<?php

use App\Http\Controllers\Settings\DocumentController;
use App\Http\Controllers\Settings\NotificationController;
use App\Http\Controllers\Settings\OvertimeController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\TokenController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');
    Route::redirect('profile', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::post('settings/security/tokens', [TokenController::class, 'store'])
        ->middleware([RequirePassword::class, 'throttle:6,1'])
        ->name('security.tokens.store');

    Route::delete('settings/security/tokens/{token}', [TokenController::class, 'destroy'])
        ->middleware([RequirePassword::class, 'throttle:6,1'])
        ->whereNumber('token')
        ->name('security.tokens.destroy');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});

// The organization-wide Settings sections (KOL-123): everyone reaches
// /settings/*, but only an admin sees or edits these.
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('settings/notifications', [NotificationController::class, 'edit'])->name('settings-notifications.edit');
    Route::patch('settings/notifications', [NotificationController::class, 'update'])->name('settings-notifications.update');

    Route::get('settings/documents', [DocumentController::class, 'edit'])->name('settings-documents.edit');
    Route::patch('settings/documents', [DocumentController::class, 'update'])->name('settings-documents.update');

    Route::get('settings/overtime', [OvertimeController::class, 'edit'])->name('settings-overtime.edit');
    Route::patch('settings/overtime', [OvertimeController::class, 'update'])->name('settings-overtime.update');
});
