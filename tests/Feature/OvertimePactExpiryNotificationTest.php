<?php

use App\Models\Organization;
use App\Models\OvertimePact;
use App\Models\User;
use App\Notifications\OvertimePactNearingExpiry;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('every user managing pactos is notified when one nears its end date', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    $pact = OvertimePact::factory()->create([
        'organization_id' => $organization->id,
        'end_date' => now()->addDays(3)->toDateString(),
    ]);

    $this->artisan('overtime:pacts:notify-expiring')->assertSuccessful();

    Notification::assertSentTo($admin, OvertimePactNearingExpiry::class);
    expect($pact->fresh()->expiry_notified_at)->not->toBeNull();
});

test('a pacto outside the near-expiry window is not notified about', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    OvertimePact::factory()->create([
        'organization_id' => $organization->id,
        'end_date' => now()->addDays(30)->toDateString(),
    ]);

    $this->artisan('overtime:pacts:notify-expiring')->assertSuccessful();

    Notification::assertNothingSent();
});

test('a pacto already notified is never notified twice', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    $pact = OvertimePact::factory()->create([
        'organization_id' => $organization->id,
        'end_date' => now()->addDays(3)->toDateString(),
        'expiry_notified_at' => now(),
    ]);

    $this->artisan('overtime:pacts:notify-expiring')->assertSuccessful();

    Notification::assertNothingSent();
});

test('a revoked pacto nearing its end date is not notified about', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    OvertimePact::factory()->revoked()->create([
        'organization_id' => $organization->id,
        'end_date' => now()->addDays(3)->toDateString(),
    ]);

    $this->artisan('overtime:pacts:notify-expiring')->assertSuccessful();

    Notification::assertNothingSent();
});
