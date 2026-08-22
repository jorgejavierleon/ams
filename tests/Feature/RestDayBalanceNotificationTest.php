<?php

use App\Models\Organization;
use App\Models\OvertimeRestDayBalance;
use App\Models\User;
use App\Notifications\RestDayBalanceAccrued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('an employee carrying a spendable rest-day balance is mailed and stamped', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $employee = User::factory()->create(['organization_id' => $organization->id]);
    OvertimeRestDayBalance::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    $this->artisan('overtime:rest-day-balances:notify')->assertSuccessful();

    Notification::assertSentTo($employee, RestDayBalanceAccrued::class);
    expect($employee->fresh()->rest_day_balance_notified_at)->not->toBeNull();
});

test('an employee with no rest-day balance is not mailed', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    User::factory()->create(['organization_id' => $organization->id]);

    $this->artisan('overtime:rest-day-balances:notify')->assertSuccessful();

    Notification::assertNothingSent();
});

test('a fully consumed balance does not count as spendable', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $employee = User::factory()->create(['organization_id' => $organization->id]);
    OvertimeRestDayBalance::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
        'rest_hours' => '03:00:00',
        'consumed_hours' => '03:00:00',
    ]);

    $this->artisan('overtime:rest-day-balances:notify')->assertSuccessful();

    Notification::assertNothingSent();
});

test('an employee notified within the last 30 days is not mailed again', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $employee = User::factory()->create([
        'organization_id' => $organization->id,
        'rest_day_balance_notified_at' => now()->subDays(29),
    ]);
    OvertimeRestDayBalance::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    $this->artisan('overtime:rest-day-balances:notify')->assertSuccessful();

    Notification::assertNothingSent();
});

test('an employee last notified 30 or more days ago is mailed again', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $employee = User::factory()->create([
        'organization_id' => $organization->id,
        'rest_day_balance_notified_at' => now()->subDays(30),
    ]);
    OvertimeRestDayBalance::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    $this->artisan('overtime:rest-day-balances:notify')->assertSuccessful();

    Notification::assertSentTo($employee, RestDayBalanceAccrued::class);
});

test('a same-day re-run does not mail the same employee twice', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $employee = User::factory()->create(['organization_id' => $organization->id]);
    OvertimeRestDayBalance::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $employee->id,
    ]);

    $this->artisan('overtime:rest-day-balances:notify')->assertSuccessful();
    Notification::assertSentToTimes($employee, RestDayBalanceAccrued::class, 1);

    $this->artisan('overtime:rest-day-balances:notify')->assertSuccessful();
    Notification::assertSentToTimes($employee, RestDayBalanceAccrued::class, 1);
});
