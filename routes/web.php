<?php

use App\Http\Controllers\CommuneController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\Dt\ForgotPasswordController;
use App\Http\Controllers\Dt\LoginController;
use App\Http\Controllers\Dt\PasswordChangeController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\My\LeaveController as MyLeaveController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\PremiseController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Saas\HolidayController as SaasHolidayController;
use App\Http\Controllers\Saas\LoginController as SaasLoginController;
use App\Http\Controllers\Saas\OrganizationController;
use App\Http\Controllers\ShiftAssignmentController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Switch the active UI locale (persisted in the session, applied by SetLocale)
Route::put('locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

// Admin panel routes (role:admin required)
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');
    Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');

    Route::get('users/{user}/roles', [UserRoleController::class, 'show'])->name('users.roles');
    Route::put('users/{user}/roles', [UserRoleController::class, 'update'])->name('users.roles.update');

    Route::resource('positions', PositionController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);

    Route::resource('companies', CompanyController::class)
        ->except(['show']);

    Route::resource('premises', PremiseController::class)
        ->except(['show']);

    Route::resource('shifts', ShiftController::class)
        ->except(['show']);

    Route::resource('holidays', HolidayController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::patch('employees/{employee}/active', [EmployeeController::class, 'toggleActive'])
        ->name('employees.toggle-active');
    Route::resource('employees', EmployeeController::class);

    Route::post('employees/{employee}/shift-assignments', [ShiftAssignmentController::class, 'store'])
        ->name('employees.shift-assignments.store');
    Route::patch('shift-assignments/{shiftAssignment}/end', [ShiftAssignmentController::class, 'end'])
        ->name('shift-assignments.end');
    Route::delete('shift-assignments/{shiftAssignment}', [ShiftAssignmentController::class, 'destroy'])
        ->name('shift-assignments.destroy');

    Route::resource('leaves', LeaveController::class)
        ->only(['index', 'create', 'store', 'destroy'])
        ->parameter('leaves', 'leave');
    Route::get('leaves/business-days', [LeaveController::class, 'businessDays'])
        ->name('leaves.business-days');
    Route::post('leaves/{leave}/approve', [LeaveController::class, 'approve'])
        ->name('leaves.approve');
    Route::post('leaves/{leave}/reject', [LeaveController::class, 'reject'])
        ->name('leaves.reject');

    Route::get('regions/{region}/communes', [CommuneController::class, 'index'])
        ->name('regions.communes');
});

// Employee self-service routes (gated by Spatie permissions, not roles)
Route::middleware(['auth', 'verified'])->prefix('my')->name('my.')->group(function () {
    Route::get('leaves', [MyLeaveController::class, 'index'])
        ->middleware('permission:ViewOwn:Leave')
        ->name('leaves.index');
    Route::get('leaves/create', [MyLeaveController::class, 'create'])
        ->middleware('permission:RequestOwn:Leave')
        ->name('leaves.create');
    Route::post('leaves', [MyLeaveController::class, 'store'])
        ->middleware('permission:RequestOwn:Leave')
        ->name('leaves.store');
    Route::get('leaves/business-days', [MyLeaveController::class, 'businessDays'])
        ->middleware('permission:RequestOwn:Leave')
        ->name('leaves.business-days');
    Route::delete('leaves/{leave}', [MyLeaveController::class, 'destroy'])
        ->middleware('permission:CancelOwn:Leave')
        ->name('leaves.destroy');
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

// SaaS panel routes
Route::prefix('saas')->name('saas.')->group(function () {
    // Guest routes (unauthenticated SaaS users)
    Route::middleware('guest:saas')->group(function () {
        Route::get('login', [SaasLoginController::class, 'create'])->name('login');
        Route::post('login', [SaasLoginController::class, 'store']);
    });

    // Authenticated SaaS routes
    Route::middleware(['auth:saas'])->group(function () {
        Route::post('logout', [SaasLoginController::class, 'destroy'])->name('logout');

        Route::inertia('dashboard', 'saas/dashboard')->name('dashboard');

        // Super-admin management (saas role required)
        Route::middleware('role:saas,saas')->group(function () {
            Route::resource('organizations', OrganizationController::class)->except('show');

            Route::get('holidays', [SaasHolidayController::class, 'index'])->name('holidays.index');
            Route::post('holidays/sync', [SaasHolidayController::class, 'sync'])->name('holidays.sync');
        });
    });
});

require __DIR__.'/settings.php';
