<?php

use App\Http\Controllers\Api\MarkController;
use App\Http\Controllers\Api\TokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public: exchange employee credentials for a device bearer token.
Route::post('/sanctum/token', [TokenController::class, 'issueToken']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', fn (Request $request) => $request->user());

    // Mirror the web permission model: clocking needs ClockOwn:Mark, reading
    // needs ViewOwn:Mark.
    Route::post('marks', [MarkController::class, 'store'])
        ->middleware('permission:ClockOwn:Mark')
        ->name('marks.store');

    Route::get('marks', [MarkController::class, 'index'])
        ->middleware('permission:ViewOwn:Mark')
        ->name('marks.index');

    Route::get('marks/{mark}', [MarkController::class, 'show'])
        ->middleware('permission:ViewOwn:Mark')
        ->name('marks.show');
});
