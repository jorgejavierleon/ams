<?php

use App\Http\Controllers\CommuneController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CostCenterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentSignatureController;
use App\Http\Controllers\DocumentTemplateController;
use App\Http\Controllers\Dt\DocumentController as DtDocumentController;
use App\Http\Controllers\Dt\ForgotPasswordController;
use App\Http\Controllers\Dt\LoginController;
use App\Http\Controllers\Dt\MarkValidationController;
use App\Http\Controllers\Dt\OrganizationController as DtOrganizationController;
use App\Http\Controllers\Dt\PasswordChangeController;
use App\Http\Controllers\Dt\ReportController as DtReportController;
use App\Http\Controllers\Dt\ReportExportDownloadController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\LeaveCalendarController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MarkModificationReviewController;
use App\Http\Controllers\My\DocumentController as MyDocumentController;
use App\Http\Controllers\My\LeaveController as MyLeaveController;
use App\Http\Controllers\My\MarkController as MyMarkController;
use App\Http\Controllers\My\OvertimeRequestController as MyOvertimeRequestController;
use App\Http\Controllers\My\OvertimeRestDayBalanceController as MyOvertimeRestDayBalanceController;
use App\Http\Controllers\My\WorkdayController as MyWorkdayController;
use App\Http\Controllers\OvertimeController;
use App\Http\Controllers\OvertimeExcessReportController;
use App\Http\Controllers\OvertimePactController;
use App\Http\Controllers\OvertimeRequestController;
use App\Http\Controllers\OvertimeRestDayBalanceController;
use App\Http\Controllers\PayrollSummaryReportController;
use App\Http\Controllers\PeriodMovementsReportController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\PremiseController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Saas\AuditLogController;
use App\Http\Controllers\Saas\DocumentVarController;
use App\Http\Controllers\Saas\HolidayController as SaasHolidayController;
use App\Http\Controllers\Saas\LegalHourLimitController;
use App\Http\Controllers\Saas\LoginController as SaasLoginController;
use App\Http\Controllers\Saas\OrganizationController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ShiftAssignmentController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\UserRoleController;
use App\Http\Controllers\WeeklyDetailReportController;
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

    Route::get('organization-settings', [SettingController::class, 'index'])->name('organization-settings.edit');
    Route::patch('organization-settings', [SettingController::class, 'update'])->name('organization-settings.update');

    Route::resource('positions', PositionController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);

    Route::resource('cost-centers', CostCenterController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    // One employer per organization (KOL-32) — a singleton form, not a resource.
    Route::get('company', [CompanyController::class, 'edit'])->name('company.edit');
    Route::put('company', [CompanyController::class, 'update'])->name('company.update');

    Route::resource('premises', PremiseController::class)
        ->except(['show']);

    Route::resource('shifts', ShiftController::class)
        ->except(['show']);

    Route::resource('holidays', HolidayController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::patch('employees/{employee}/active', [EmployeeController::class, 'toggleActive'])
        ->name('employees.toggle-active');
    Route::get('employees/export/{format}', [EmployeeController::class, 'export'])
        ->name('employees.export');
    Route::resource('employees', EmployeeController::class);

    Route::post('employees/{employee}/shift-assignments', [ShiftAssignmentController::class, 'store'])
        ->name('employees.shift-assignments.store');
    Route::patch('shift-assignments/{shiftAssignment}/end', [ShiftAssignmentController::class, 'end'])
        ->name('shift-assignments.end');
    Route::delete('shift-assignments/{shiftAssignment}', [ShiftAssignmentController::class, 'destroy'])
        ->name('shift-assignments.destroy');

    Route::post('documents/{document}/publish', [DocumentController::class, 'publish'])
        ->name('documents.publish');
    Route::post('documents/{document}/void', [DocumentController::class, 'void'])
        ->name('documents.void');
    Route::post('documents/{document}/duplicate', [DocumentController::class, 'duplicate'])
        ->name('documents.duplicate');
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])
        ->name('documents.download');
    Route::post('document-signatures/{documentSignature}/resend', [DocumentSignatureController::class, 'resend'])
        ->name('document-signatures.resend');
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

// Jornadas (KOL-71). Shared by admins and supervisors, same shape as the leave
// review routes below: authorization is enforced per request in
// WorkdayController/WorkdayPolicy (ViewAny/Update:Workday org-wide,
// ViewTeam/ApproveTeam:Workday scoped to a supervisor's own direct reports),
// not by a route-level permission gate — admins reach every action through
// the super-admin Gate::before bypass without needing either permission
// granted explicitly.
Route::middleware(['auth', 'verified'])->group(function () {
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

    // KOL-71: overtime approval lives on Jornadas, next to mark-modification
    // review, rather than on the separate overtime queue.
    Route::post('workdays/overtime/bulk-decide', [WorkdayController::class, 'bulkDecideOvertime'])
        ->name('workdays.overtime.bulk-decide');
    Route::post('workdays/{workday}/overtime/approve', [WorkdayController::class, 'approveOvertime'])
        ->name('workdays.overtime.approve');
    Route::post('workdays/{workday}/overtime/revoke', [WorkdayController::class, 'revokeOvertime'])
        ->name('workdays.overtime.revoke');
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

// Overtime section (KOL-43). Shared by every role that holds one of its
// permissions — the queue (KOL-44) and request flow (KOL-45) add their own
// routes under this same gate as they land.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('overtime', [OvertimeController::class, 'index'])
        ->middleware('permission:RequestOwn:OvertimeAuthorization|ViewOwn:OvertimeAuthorization|ViewTeam:OvertimeAuthorization|ApproveTeam:OvertimeAuthorization|Manage:OvertimeAuthorization')
        ->name('overtime.index');

    // Pactos de horas extraordinarias (KOL-42): managed only by whoever holds
    // Manage:OvertimeAuthorization (KOL-43), the same permission that gates
    // the section's admin-only actions.
    Route::middleware('permission:Manage:OvertimeAuthorization')
        ->prefix('overtime/pacts')
        ->name('overtime.pacts.')
        ->group(function () {
            Route::get('/', [OvertimePactController::class, 'index'])->name('index');
            Route::post('/', [OvertimePactController::class, 'store'])->name('store');
            Route::put('/{overtimePact}', [OvertimePactController::class, 'update'])->name('update');
            Route::patch('/{overtimePact}/revoke', [OvertimePactController::class, 'revoke'])->name('revoke');
            Route::patch('/{overtimePact}/activate', [OvertimePactController::class, 'activate'])->name('activate');
        });

    // Mode A overtime requests (KOL-72), extracted from the queue's
    // Solicitudes tab into their own screen — a request isn't tied to a
    // computed Workday, so it doesn't belong on Jornadas (KOL-71) either.
    // Reachable by whoever can see a team's requests; deciding one further
    // requires ApproveTeam, enforced in OvertimeRequestController/Policy.
    Route::middleware('permission:ViewTeam:OvertimeAuthorization|Manage:OvertimeAuthorization')
        ->prefix('overtime/requests')
        ->name('overtime.requests.')
        ->group(function () {
            Route::get('/', [OvertimeRequestController::class, 'index'])->name('index');
            Route::post('/{overtimeRequest}/approve', [OvertimeRequestController::class, 'approve'])->name('approve');
            Route::post('/{overtimeRequest}/reject', [OvertimeRequestController::class, 'reject'])->name('reject');
        });

    // Rest-day compensation balances (KOL-47): HR/admin view and consumption,
    // gated by the same permission as pactos since the two are managed by the
    // same people.
    Route::middleware('permission:Manage:OvertimeAuthorization')
        ->prefix('overtime/rest-day-balances')
        ->name('overtime.rest-day-balances.')
        ->group(function () {
            Route::get('/', [OvertimeRestDayBalanceController::class, 'index'])->name('index');
            Route::post('/consume', [OvertimeRestDayBalanceController::class, 'consume'])->name('consume');
        });
});

// Payroll reports section (KOL-18): the container for the five RF-1 reports
// (KOL-20..24). Gated on its own permissions rather than role:admin — tenant
// users (RRHH/admin), separate from the DT inspector's `dt.reports.*`. The
// landing page (a report-type picker) is gone for now (KOL-20 UI redesign)
// since only one report existed — now that KOL-21/22/24 land more, all are
// reachable from the nav (app-sidebar.tsx) instead. Revisit once a picker
// (or sub-nav) is actually needed again.
Route::middleware(['auth', 'verified', 'permission:View:PayrollReport'])
    ->prefix('payroll-reports')
    ->name('payroll-reports.')
    ->group(function () {
        Route::get('summary', [PayrollSummaryReportController::class, 'index'])->name('summary');
        Route::get('weekly-detail', [WeeklyDetailReportController::class, 'index'])->name('weekly-detail');
        Route::get('period-movements', [PeriodMovementsReportController::class, 'index'])->name('period-movements');
        Route::get('overtime-excess', [OvertimeExcessReportController::class, 'index'])->name('overtime-excess');
    });

// Producing the actual export file is a more sensitive action than viewing
// the on-screen report, so it holds its own permission (RoleSeeder: `View`
// and `Export` are deliberately separate for PayrollReport).
Route::middleware(['auth', 'verified', 'permission:Export:PayrollReport'])
    ->prefix('payroll-reports')
    ->name('payroll-reports.')
    ->group(function () {
        Route::get('summary/export/{format}', [PayrollSummaryReportController::class, 'export'])->name('summary.export');
        Route::get('weekly-detail/export/{format}', [WeeklyDetailReportController::class, 'export'])->name('weekly-detail.export');
        Route::get('period-movements/export/{format}', [PeriodMovementsReportController::class, 'export'])->name('period-movements.export');
        Route::get('overtime-excess/export/{format}', [OvertimeExcessReportController::class, 'export'])->name('overtime-excess.export');
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

    Route::get('overtime-requests', [MyOvertimeRequestController::class, 'index'])
        ->middleware('permission:ViewOwn:OvertimeAuthorization')
        ->name('overtime-requests.index');
    Route::get('overtime-requests/create', [MyOvertimeRequestController::class, 'create'])
        ->middleware('permission:RequestOwn:OvertimeAuthorization')
        ->name('overtime-requests.create');
    Route::post('overtime-requests', [MyOvertimeRequestController::class, 'store'])
        ->middleware('permission:RequestOwn:OvertimeAuthorization')
        ->name('overtime-requests.store');

    Route::get('overtime-rest-day-balance', [MyOvertimeRestDayBalanceController::class, 'index'])
        ->middleware('permission:ViewOwn:OvertimeAuthorization')
        ->name('overtime-rest-day-balance.index');

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

    Route::get('documents', [MyDocumentController::class, 'index'])
        ->middleware('permission:ViewOwn:Document')
        ->name('documents.index');
    Route::get('documents/{document}', [MyDocumentController::class, 'show'])
        ->middleware('permission:ViewOwn:Document')
        ->name('documents.show');
    Route::get('documents/{document}/download', [MyDocumentController::class, 'download'])
        ->middleware('permission:ViewOwn:Document')
        ->name('documents.download');
    Route::post('documents/{document}/send-code', [MyDocumentController::class, 'sendCode'])
        ->middleware('permission:SignOwn:Document')
        ->name('documents.send-code');
    Route::post('documents/{document}/sign', [MyDocumentController::class, 'sign'])
        ->middleware('permission:SignOwn:Document')
        ->name('documents.sign');
    Route::post('documents/{document}/reject', [MyDocumentController::class, 'reject'])
        ->middleware('permission:SignOwn:Document')
        ->name('documents.reject');
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

                // Read-only employment-documents list for the audited employer,
                // with a per-document PDF preview download (Resolución 38).
                Route::get('documents', [DtDocumentController::class, 'index'])->name('documents.index');
                Route::get('documents/{document}/download', [DtDocumentController::class, 'download'])->name('documents.download');
                Route::get('documents/{document}', [DtDocumentController::class, 'show'])->name('documents.show');

                // Compliance reports (Resolución 38). The section landing page
                // hosts the shared filter UI; each report type has its own route
                // that pre-selects the filter and renders its table (#39–#43).
                Route::prefix('reports')->name('reports.')->group(function () {
                    Route::get('/', [DtReportController::class, 'index'])->name('index');
                    Route::get('attendance', [DtReportController::class, 'attendance'])->name('attendance');
                    Route::get('daily', [DtReportController::class, 'daily'])->name('daily');
                    Route::get('shift-changes', [DtReportController::class, 'shiftChanges'])->name('shift-changes');
                    Route::get('sundays', [DtReportController::class, 'sundays'])->name('sundays');
                    Route::get('incidents', [DtReportController::class, 'incidents'])->name('incidents');

                    // Excel / PDF / Word download for any report (Resolución 38,
                    // Art. 28 b), streamed directly rather than via Inertia. A
                    // selection above the configured threshold is queued instead
                    // (KOL-16) and delivered through the route below.
                    Route::get('{type}/export', [DtReportController::class, 'export'])->name('export');

                    // The signed, expiring link mailed once a queued export
                    // finishes rendering (KOL-16 AC #4): a real HTML landing
                    // page (not a raw file response — see
                    // ReportExportDownloadController) with a button to the
                    // actual file, served just like documents.download below.
                    Route::get('exports/{reportExport}', [ReportExportDownloadController::class, 'show'])
                        ->name('exports.show')
                        ->middleware('signed');
                    Route::get('exports/{reportExport}/download', [ReportExportDownloadController::class, 'download'])
                        ->name('exports.download');
                });
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

            // The global legal working-hour limits. No destroy route: a version
            // a calculated day was judged against is never removed, and a
            // mistaken one is fixed through the correction flow below, which
            // recalculates the days it affected.
            Route::get('legal-hour-limits', [LegalHourLimitController::class, 'index'])->name('legal-hour-limits.index');
            Route::get('legal-hour-limits/create', [LegalHourLimitController::class, 'create'])->name('legal-hour-limits.create');
            Route::post('legal-hour-limits', [LegalHourLimitController::class, 'store'])->name('legal-hour-limits.store');
            Route::get('legal-hour-limits/{legalHourLimit}/correct', [LegalHourLimitController::class, 'correct'])->name('legal-hour-limits.correct');
            Route::put('legal-hour-limits/{legalHourLimit}', [LegalHourLimitController::class, 'update'])->name('legal-hour-limits.update');

            Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
        });
    });
});

require __DIR__.'/settings.php';
