<?php

use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Spatie\Activitylog\Models\Activity;

uses()->group('saas');

function saasAuditAdmin(): User
{
    return User::factory()->saasUser()->create();
}

function logAudit(User $causer, string $description, ?Carbon $at = null): Activity
{
    activity()
        ->causedBy($causer)
        ->event('updated')
        ->withProperties(['old' => ['status' => 'draft'], 'attributes' => ['status' => 'published']])
        ->log($description);

    $activity = Activity::query()->latest('id')->first();

    if ($at !== null) {
        $activity->forceFill(['created_at' => $at])->save();
    }

    return $activity;
}

// --- Access control ---

test('unauthenticated users are redirected to saas login', function () {
    $this->get(route('saas.audit-log.index'))->assertRedirect('/saas/login');
});

test('non-saas users are denied access to the audit log', function () {
    $this->actingAs(User::factory()->create(), 'saas')
        ->get(route('saas.audit-log.index'))
        ->assertForbidden();
});

// --- Index ---

test('the audit log lists activity entries with causer details', function () {
    $causer = saasAuditAdmin();
    logAudit($causer, 'Something happened');

    $this->actingAs($causer, 'saas')
        ->get(route('saas.audit-log.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('saas/audit-log/index')
                ->has('activities.data', 1)
                ->where('activities.data.0.description', 'Something happened')
                ->where('activities.data.0.event', 'updated')
                ->where('activities.data.0.causer.name', $causer->name)
                ->where('activities.data.0.causer.email', $causer->email)
                ->has('activities.data.0.properties'),
        );
});

test('the date range filter narrows the results', function () {
    $causer = saasAuditAdmin();
    logAudit($causer, 'Old entry', Carbon::parse('2026-01-01 10:00:00'));
    logAudit($causer, 'Recent entry', Carbon::parse('2026-06-01 10:00:00'));

    $this->actingAs($causer, 'saas')
        ->get(route('saas.audit-log.index', ['date_from' => '2026-05-01']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('activities.data', 1)
                ->where('activities.data.0.description', 'Recent entry'),
        );
});

test('the causer filter narrows the results', function () {
    $admin = saasAuditAdmin();
    $other = User::factory()->create();

    logAudit($admin, 'By admin');
    logAudit($other, 'By other');

    $this->actingAs($admin, 'saas')
        ->get(route('saas.audit-log.index', ['causer_id' => $other->id]))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('activities.data', 1)
                ->where('activities.data.0.description', 'By other'),
        );
});

test('the organization filter narrows the results by the causer organization', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $userA = User::factory()->create(['organization_id' => $orgA->id]);
    $userB = User::factory()->create(['organization_id' => $orgB->id]);

    logAudit($userA, 'From org A');
    logAudit($userB, 'From org B');

    $this->actingAs(saasAuditAdmin(), 'saas')
        ->get(route('saas.audit-log.index', ['organization_id' => $orgB->id]))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('activities.data', 1)
                ->where('activities.data.0.description', 'From org B'),
        );
});

test('an invalid date range is rejected', function () {
    $this->actingAs(saasAuditAdmin(), 'saas')
        ->get(route('saas.audit-log.index', ['date_from' => '2026-06-01', 'date_to' => '2026-01-01']))
        ->assertSessionHasErrors('date_to');
});
