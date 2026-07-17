<?php

use App\Http\Controllers\CommuneController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentTemplateController;
use App\Http\Controllers\Dt\DocumentController as DtDocumentController;
use App\Http\Controllers\Dt\ForgotPasswordController;
use App\Http\Controllers\Dt\IncidentController as DtIncidentController;
use App\Http\Controllers\Dt\LoginController;
use App\Http\Controllers\Dt\MarkValidationController;
use App\Http\Controllers\Dt\OrganizationController as DtOrganizationController;
use App\Http\Controllers\Dt\PasswordChangeController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\LeaveCalendarController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MarkModificationReviewController;
use App\Http\Controllers\My\LeaveController as MyLeaveController;
use App\Http\Controllers\My\MarkController as MyMarkController;
use App\Http\Controllers\My\WorkdayController as MyWorkdayController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\PremiseController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Saas\DocumentVarController;
use App\Http\Controllers\Saas\HolidayController as SaasHolidayController;
use App\Http\Controllers\Saas\LoginController as SaasLoginController;
use App\Http\Controllers\Saas\OrganizationController;
use App\Http\Controllers\ShiftAssignmentController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\UserRoleController;
use App\Http\Controllers\WorkdayController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Switch the active UI locale (persisted in the session, applied by SetLocale)
Route::put('locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');

// Public, no-auth mark-modification review. Employees reach these through the
// ULID link emailed to them and approve or decline the correction without
// logging in, so the routes sit outside every authenticated group.
Route::prefix('mark-modifications/{modification:ulid}')->name('mark-modifications.')->group(function () {
    Route::get('/', [MarkModificationReviewController::class, 'show'])->name('review');
    Route::post('approve', [MarkModificationReviewController::class, 'approve'])->name('approve');
    Route::post('decline', [MarkModificationReviewController::class, 'decline'])->name('decline');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
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

    Route::get('workdays', [WorkdayController::class, 'index'])->name('workdays.index');
    Route::get('workdays/{workday}', [WorkdayController::class, 'show'])->name('workdays.show');
    Route::post('workdays/bulk-modify', [WorkdayController::class, 'bulkModify'])
        ->name('workdays.bulk-modify');
    Route::post('workdays/{workday}/modify', [WorkdayController::class, 'modify'])
        ->name('workdays.modify');
    Route::post('workdays/{workday}/modifications/{markModification}/approve', [WorkdayController::class, 'approveModification'])
        ->scopeBindings()
        ->name('workdays.modifications.approve');
    Route::post('workdays/{workday}/modifications/{markModification}/decline', [WorkdayController::class, 'declineModification'])
        ->scopeBindings()
        ->name('workdays.modifications.decline');

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

    Route::post('documents/{document}/publish', [DocumentController::class, 'publish'])
        ->name('documents.publish');
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])
        ->name('documents.download');
    Route::resource('documents', DocumentController::class);

    Route::get('document-templates/{documentTemplate}/body', [DocumentTemplateController::class, 'body'])
        ->name('document-templates.body');
    Route::patch('document-templates/{documentTemplate}/restore', [DocumentTemplateController::class, 'restore'])
        ->name('document-templates.restore');
    Route::resource('document-templates', DocumentTemplateController::class)
        ->except(['show']);

    Route::resource('leaves', LeaveController::class)
        ->only(['create', 'store', 'destroy'])
        ->parameter('leaves', 'leave');
    Route::get('leaves/business-days', [LeaveController::class, 'businessDays'])
        ->name('leaves.business-days');

    Route::get('regions/{region}/communes', [CommuneController::class, 'index'])
        ->name('regions.communes');
});

// Leave review routes shared by admins and supervisors. Authorization is
// enforced per request in the controller/LeavePolicy: admins see every leave,
// supervisors only their own team.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('leaves', [LeaveController::class, 'index'])
        ->name('leaves.index');
    Route::get('leaves/calendar', [LeaveCalendarController::class, 'index'])
        ->name('leaves.calendar');
    Route::get('api/leaves/calendar', [LeaveCalendarController::class, 'events'])
        ->name('leaves.calendar.events');
    Route::post('leaves/{leave}/approve', [LeaveController::class, 'approve'])
        ->name('leaves.approve');
    Route::post('leaves/{leave}/reject', [LeaveController::class, 'reject'])
        ->name('leaves.reject');
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

    Route::post('marks', [MyMarkController::class, 'store'])
        ->middleware('permission:ClockOwn:Mark')
        ->name('marks.store');

    Route::get('workdays', [MyWorkdayController::class, 'index'])
        ->middleware('permission:ViewOwn:Workday')
        ->name('workdays.index');
    Route::get('workdays/{workday}', [MyWorkdayController::class, 'show'])
        ->middleware('permission:ViewOwn:Workday')
        ->name('workdays.show');
    Route::post('workdays/{workday}/modifications/{markModification}/approve', [MyWorkdayController::class, 'approveModification'])
        ->scopeBindings()
        ->middleware('permission:ReviewOwn:MarkModification')
        ->name('workdays.modifications.approve');
    Route::post('workdays/{workday}/modifications/{markModification}/decline', [MyWorkdayController::class, 'declineModification'])
        ->scopeBindings()
        ->middleware('permission:ReviewOwn:MarkModification')
        ->name('workdays.modifications.decline');
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
            // Audit session organization selector — the entry point that gates
            // every organization-scoped view below, so it stays ungated itself.
            Route::get('select-organization', [DtOrganizationController::class, 'index'])->name('organization.select');
            Route::post('select-organization', [DtOrganizationController::class, 'store'])->name('organization.store');

            // Validate a printed attendance proof by its SHA-256 checksum. This
            // tool spans every employer, so it needs no audit organization.
            Route::get('marks/validate', [MarkValidationController::class, 'create'])->name('marks.validate');
            Route::post('marks/validate', [MarkValidationController::class, 'store'])->name('marks.validate.store');

            // Organization-scoped views require an active audit session.
            Route::middleware('dt_organization_selected')->group(function () {
                Route::inertia('dashboard', 'dt/dashboard')->name('dashboard');

                // Read-only technical-incidents list for the audited employer.
                Route::get('incidents', [DtIncidentController::class, 'index'])->name('incidents.index');

                // Read-only employment-documents list for the audited employer,
                // with a per-document PDF preview download (Resolución 38).
                Route::get('documents', [DtDocumentController::class, 'index'])->name('documents.index');
                Route::get('documents/{document}/download', [DtDocumentController::class, 'download'])->name('documents.download');
                Route::get('documents/{document}', [DtDocumentController::class, 'show'])->name('documents.show');
            });
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

            Route::resource('document-variables', DocumentVarController::class)->except('show');

            Route::get('holidays', [SaasHolidayController::class, 'index'])->name('holidays.index');
            Route::post('holidays/sync', [SaasHolidayController::class, 'sync'])->name('holidays.sync');
        });
    });
});

require __DIR__.'/settings.php';
