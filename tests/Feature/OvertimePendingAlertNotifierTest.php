<?php

use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workday;
use App\Notifications\OvertimePactNearingExpiry;
use App\Notifications\OvertimePendingOvertimeAlert;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/**
 * KOL-52, PRD §12: the recurring digest, run daily per organization. Unlike
 * {@see OvertimePactNearingExpiry} this has no per-record
 * dedup — it is meant to keep reminding for as long as the condition holds.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function pendingAlertAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

function pendingAlertStaleWorkday(User $employee, int $daysAgo): Workday
{
    return Workday::factory()->create([
        'organization_id' => $employee->organization_id,
        'user_id' => $employee->id,
        'date' => now()->subDays($daysAgo),
        'calculated_overtime' => '01:00:00',
    ]);
}

test('an organization with stale overtime notifies every user managing it', function () {
    Notification::fake();

    $admin = pendingAlertAdmin();
    $employee = User::factory()->employee()->create(['organization_id' => $admin->organization_id]);
    pendingAlertStaleWorkday($employee, daysAgo: 20);

    Setting::factory()->create([
        'organization_id' => $admin->organization_id,
        'overtime_pending_alert_threshold_days' => 15,
    ]);

    $this->artisan('overtime:pending:notify-stale')->assertSuccessful();

    Notification::assertSentTo($admin, OvertimePendingOvertimeAlert::class, function (OvertimePendingOvertimeAlert $notification) {
        return $notification->staleCount === 1
            && $notification->thresholdDays === 15
            && $notification->oldestDaysPending === 20;
    });
});

test('an organization with nothing stale is not notified', function () {
    Notification::fake();

    $admin = pendingAlertAdmin();
    $employee = User::factory()->employee()->create(['organization_id' => $admin->organization_id]);
    pendingAlertStaleWorkday($employee, daysAgo: 5);

    Setting::factory()->create([
        'organization_id' => $admin->organization_id,
        'overtime_pending_alert_threshold_days' => 15,
    ]);

    $this->artisan('overtime:pending:notify-stale')->assertSuccessful();

    Notification::assertNothingSent();
});

test('the notification re-sends every run while the condition holds, unlike the pacto alert', function () {
    Notification::fake();

    $admin = pendingAlertAdmin();
    $employee = User::factory()->employee()->create(['organization_id' => $admin->organization_id]);
    pendingAlertStaleWorkday($employee, daysAgo: 20);

    Setting::factory()->create([
        'organization_id' => $admin->organization_id,
        'overtime_pending_alert_threshold_days' => 15,
    ]);

    $this->artisan('overtime:pending:notify-stale')->assertSuccessful();
    $this->artisan('overtime:pending:notify-stale')->assertSuccessful();

    Notification::assertSentToTimes($admin, OvertimePendingOvertimeAlert::class, 2);
});

test('each organization is checked against its own threshold', function () {
    Notification::fake();

    $lenient = pendingAlertAdmin();
    $lenientEmployee = User::factory()->employee()->create(['organization_id' => $lenient->organization_id]);
    pendingAlertStaleWorkday($lenientEmployee, daysAgo: 10);
    Setting::factory()->create([
        'organization_id' => $lenient->organization_id,
        'overtime_pending_alert_threshold_days' => 30,
    ]);

    $strict = pendingAlertAdmin();
    $strictEmployee = User::factory()->employee()->create(['organization_id' => $strict->organization_id]);
    pendingAlertStaleWorkday($strictEmployee, daysAgo: 10);
    Setting::factory()->create([
        'organization_id' => $strict->organization_id,
        'overtime_pending_alert_threshold_days' => 5,
    ]);

    $this->artisan('overtime:pending:notify-stale')->assertSuccessful();

    Notification::assertNotSentTo($lenient, OvertimePendingOvertimeAlert::class);
    Notification::assertSentTo($strict, OvertimePendingOvertimeAlert::class);
});

test('a supervisor without Manage:OvertimeAuthorization is not notified', function () {
    Notification::fake();

    $admin = pendingAlertAdmin();
    $supervisor = User::factory()->employee()->create(['organization_id' => $admin->organization_id]);
    $supervisor->assignRole('supervisor');
    pendingAlertStaleWorkday($supervisor, daysAgo: 20);

    Setting::factory()->create([
        'organization_id' => $admin->organization_id,
        'overtime_pending_alert_threshold_days' => 15,
    ]);

    $this->artisan('overtime:pending:notify-stale')->assertSuccessful();

    Notification::assertSentTo($admin, OvertimePendingOvertimeAlert::class);
    Notification::assertNotSentTo($supervisor, OvertimePendingOvertimeAlert::class);
});
