<?php

use App\Models\Organization;
use App\Models\ReportExport;
use App\Models\User;
use App\Notifications\ReportExportFailed;
use App\Notifications\ReportExportReady;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The in-app bell's backend (KOL-87): the report-export notifications land
 * in the database channel in the shape the frontend expects, the unread
 * list/count endpoint and the mark-as-read endpoint are scoped to the
 * requesting user only.
 */
test('a ready report export notifies the requester through the database channel', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->for($organization)->employee()->create();

    $reportExport = ReportExport::factory()->for($organization)->ready()->create([
        'user_id' => $user->id,
    ]);

    $user->notify(new ReportExportReady($reportExport));

    $notification = DatabaseNotification::query()->sole();

    expect($notification->notifiable_id)->toBe($user->id);
    expect($notification->type)->toBe(ReportExportReady::class);
    expect($notification->data)->toMatchArray([
        'kind' => 'report_export_ready',
        'report_export_id' => $reportExport->id,
    ]);
    expect($notification->data['url'])->toContain((string) $reportExport->id);
    expect($notification->read_at)->toBeNull();
});

test('a failed report export notifies the requester through the database channel', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->for($organization)->employee()->create();

    $reportExport = ReportExport::factory()->for($organization)->failed()->create([
        'user_id' => $user->id,
    ]);

    $user->notify(new ReportExportFailed($reportExport));

    $notification = DatabaseNotification::query()->sole();

    expect($notification->type)->toBe(ReportExportFailed::class);
    expect($notification->data)->toBe([
        'kind' => 'report_export_failed',
        'report_export_id' => $reportExport->id,
    ]);
});

test('the notifications endpoint returns the unread count and only the authenticated user own notifications', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $user = User::factory()->for($organization)->employee()->create();
    $otherUser = User::factory()->for($otherOrganization)->employee()->create();

    $ownExport = ReportExport::factory()->for($organization)->ready()->create(['user_id' => $user->id]);
    $otherExport = ReportExport::factory()->for($otherOrganization)->ready()->create(['user_id' => $otherUser->id]);

    $user->notify(new ReportExportReady($ownExport));
    $otherUser->notify(new ReportExportReady($otherExport));

    $response = $this->actingAs($user)->getJson(route('notifications.index'));

    $response->assertOk();
    expect($response->json('unreadCount'))->toBe(1);

    $notifications = $response->json('notifications');
    expect($notifications)->toHaveCount(1);
    expect($notifications[0]['data']['report_export_id'])->toBe($ownExport->id);
});

test('a DT guard user sees their own notifications through the same endpoint', function () {
    $organization = Organization::factory()->create();
    $inspector = User::factory()->dtUser()->create();

    $reportExport = ReportExport::factory()->for($organization)->ready()->create(['user_id' => $inspector->id]);
    $inspector->notify(new ReportExportReady($reportExport));

    $response = $this->actingAs($inspector, 'dt')->getJson(route('notifications.index'));

    $response->assertOk();
    expect($response->json('unreadCount'))->toBe(1);
});

test('marking a notification read persists and drops it from the unread count', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->for($organization)->employee()->create();

    $reportExport = ReportExport::factory()->for($organization)->ready()->create(['user_id' => $user->id]);
    $user->notify(new ReportExportReady($reportExport));

    $notification = $user->notifications()->sole();

    $this->actingAs($user)
        ->postJson(route('notifications.read', ['notification' => $notification->id]))
        ->assertOk()
        ->assertJson(['read' => true]);

    expect($notification->refresh()->read_at)->not->toBeNull();

    $response = $this->actingAs($user)->getJson(route('notifications.index'));
    expect($response->json('unreadCount'))->toBe(0);
});

test('a user cannot mark another user notification read', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->for($organization)->employee()->create();
    $otherUser = User::factory()->for($organization)->employee()->create();

    $reportExport = ReportExport::factory()->for($organization)->ready()->create(['user_id' => $otherUser->id]);
    $otherUser->notify(new ReportExportReady($reportExport));

    $notification = $otherUser->notifications()->sole();

    $this->actingAs($user)
        ->postJson(route('notifications.read', ['notification' => $notification->id]))
        ->assertNotFound();

    expect($notification->refresh()->read_at)->toBeNull();
});
