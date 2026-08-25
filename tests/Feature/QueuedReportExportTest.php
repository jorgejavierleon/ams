<?php

use App\Enums\ReportExportStatus;
use App\Jobs\GenerateReportExport;
use App\Models\Mark;
use App\Models\Organization;
use App\Models\ReportExport;
use App\Models\User;
use App\Notifications\ReportExportFailed;
use App\Notifications\ReportExportReady;
use App\Services\Reports\DtReportExporter;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

uses()->group('dt');

/**
 * One audited employer with a single worker, enough for the export to have
 * a row without needing hundreds of employees to cross the queue threshold
 * — tests force the threshold down to 0 instead (KOL-16).
 *
 * @return array{0: User, 1: Organization, 2: User}
 */
function seedQueuableOrganization(): array
{
    Mail::fake();

    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create(['name' => 'Acme Spa']);
    $employee = User::factory()->for($organization)->employee()->create();

    Mark::factory()->for($organization)->create([
        'user_id' => $employee->id,
        'date_time' => '2026-03-03 08:00:00',
    ]);

    return [$inspector, $organization, $employee];
}

test('a selection at or below the threshold still downloads directly with no queue round-trip', function () {
    [$inspector, $organization] = seedQueuableOrganization();

    Queue::fake();

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.export', [
            'type' => 'attendance',
            'format' => 'excel',
            'start' => '2026-03-01',
            'end' => '2026-03-31',
        ]))
        ->assertOk()
        ->assertDownload();

    Queue::assertNothingPushed();
    expect(ReportExport::query()->count())->toBe(0);
});

test('a selection above the threshold is queued and the user is told rather than left hanging', function () {
    [$inspector, $organization] = seedQueuableOrganization();

    config(['reports.export.queue_threshold' => 0]);
    Queue::fake();

    $response = $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.export', [
            'type' => 'attendance',
            'format' => 'excel',
            'start' => '2026-03-01',
            'end' => '2026-03-31',
        ]));

    $response->assertStatus(202);
    expect($response->json('queued'))->toBeTrue();
    expect($response->json('message'))->not->toBeEmpty();

    Queue::assertPushed(GenerateReportExport::class);
    expect(ReportExport::query()->count())->toBe(1);

    $reportExport = ReportExport::sole();
    expect($reportExport->status)->toBe(ReportExportStatus::Pending);
    expect($reportExport->organization_id)->toBe($organization->id);
    expect($reportExport->user_id)->toBe($inspector->id);
});

test('the queued job renders the file to the private disk, marks the export ready and notifies the requester', function () {
    [$inspector, $organization, $employee] = seedQueuableOrganization();

    Storage::fake('local');
    Notification::fake();

    $reportExport = ReportExport::factory()->for($organization)->create([
        'user_id' => $inspector->id,
        'type' => 'attendance',
        'format' => 'excel',
        'filters' => [
            'start' => '2026-03-01',
            'end' => '2026-03-31',
            'user_ids' => [$employee->id],
        ],
        'status' => ReportExportStatus::Pending,
    ]);

    (new GenerateReportExport($reportExport->id))->handle(app(DtReportExporter::class));

    $reportExport->refresh();

    expect($reportExport->status)->toBe(ReportExportStatus::Ready);
    expect($reportExport->disk_path)->not->toBeNull();
    expect($reportExport->expires_at)->not->toBeNull();
    Storage::disk('local')->assertExists($reportExport->disk_path);

    Notification::assertSentTo($inspector, ReportExportReady::class);
});

test('a failed export job notifies the requester instead of leaving them waiting', function () {
    [$inspector, $organization] = seedQueuableOrganization();

    Notification::fake();

    $reportExport = ReportExport::factory()->for($organization)->create([
        'user_id' => $inspector->id,
        'status' => ReportExportStatus::Pending,
    ]);

    (new GenerateReportExport($reportExport->id))->failed(new RuntimeException('boom'));

    $reportExport->refresh();

    expect($reportExport->status)->toBe(ReportExportStatus::Failed);
    expect($reportExport->failure_reason)->toBe('boom');

    Notification::assertSentTo($inspector, ReportExportFailed::class);
});

test('the emailed link lands on a real page with a download button, not a raw file response', function () {
    [$inspector, $organization] = seedQueuableOrganization();

    Storage::fake('local');
    Storage::disk('local')->put('report-exports/ready.xlsx', 'contents');

    $reportExport = ReportExport::factory()->for($organization)->create([
        'user_id' => $inspector->id,
        'status' => ReportExportStatus::Ready,
        'disk_path' => 'report-exports/ready.xlsx',
        'filename' => 'ready.xlsx',
        'expires_at' => now()->addDay(),
    ]);

    $url = URL::temporarySignedRoute(
        'dt.reports.exports.show',
        now()->addMinutes(5),
        ['reportExport' => $reportExport->id],
    );

    // A plain top-level navigation straight to a raw file response leaves a
    // fresh browser tab blank with nothing to display, and Chrome only
    // completes the download on a manual refresh — not something an end
    // user would ever discover. The mailed link must land on a real HTML
    // page instead (KOL-16).
    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get($url)
        ->assertOk()
        ->assertSee(__('ui.dt.reports.export.download'))
        ->assertSee(route('dt.reports.exports.download', ['reportExport' => $reportExport->id]), false);
});

test('an expired export is no longer downloadable', function () {
    [$inspector, $organization] = seedQueuableOrganization();

    Storage::fake('local');
    Storage::disk('local')->put('report-exports/expired.xlsx', 'contents');

    $reportExport = ReportExport::factory()->for($organization)->create([
        'user_id' => $inspector->id,
        'status' => ReportExportStatus::Ready,
        'disk_path' => 'report-exports/expired.xlsx',
        'filename' => 'expired.xlsx',
        'expires_at' => now()->subMinute(),
    ]);

    $signedShowUrl = URL::temporarySignedRoute(
        'dt.reports.exports.show',
        now()->addMinutes(5),
        ['reportExport' => $reportExport->id],
    );

    // The landing page tells the user the link lapsed rather than erroring...
    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get($signedShowUrl)
        ->assertOk()
        ->assertSee(__('ui.dt.reports.export.expired_heading'))
        ->assertDontSee(__('ui.dt.reports.export.download'));

    // ...and the file route itself still refuses to serve it, regardless.
    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.exports.download', ['reportExport' => $reportExport->id]))
        ->assertStatus(410);
});

test('a user can only download an export belonging to their own organization', function () {
    [$inspector, $organization] = seedQueuableOrganization();
    $otherOrganization = Organization::factory()->create(['name' => 'Other Co']);

    Storage::fake('local');
    Storage::disk('local')->put('report-exports/ready.xlsx', 'contents');

    $reportExport = ReportExport::factory()->for($organization)->create([
        'user_id' => $inspector->id,
        'status' => ReportExportStatus::Ready,
        'disk_path' => 'report-exports/ready.xlsx',
        'filename' => 'ready.xlsx',
        'expires_at' => now()->addDay(),
    ]);

    $downloadUrl = route('dt.reports.exports.download', ['reportExport' => $reportExport->id]);

    // Same inspector, but auditing a different organization when the link is
    // opened: the global organization scope must not resolve the other
    // employer's export (KOL-16 AC #6).
    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $otherOrganization->id])
        ->get($downloadUrl)
        ->assertNotFound();

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get($downloadUrl)
        ->assertDownload('ready.xlsx');
});

test('the prune command deletes the file and row for every expired export, leaving unexpired ones alone', function () {
    [, $organization] = seedQueuableOrganization();

    Storage::fake('local');
    Storage::disk('local')->put('report-exports/expired.xlsx', 'contents');
    Storage::disk('local')->put('report-exports/still-valid.xlsx', 'contents');

    $expired = ReportExport::factory()->for($organization)->create([
        'status' => ReportExportStatus::Ready,
        'disk_path' => 'report-exports/expired.xlsx',
        'filename' => 'expired.xlsx',
        'expires_at' => now()->subMinute(),
    ]);

    $stillValid = ReportExport::factory()->for($organization)->create([
        'status' => ReportExportStatus::Ready,
        'disk_path' => 'report-exports/still-valid.xlsx',
        'filename' => 'still-valid.xlsx',
        'expires_at' => now()->addDay(),
    ]);

    $this->artisan('report-exports:prune-expired')->assertSuccessful();

    Storage::disk('local')->assertMissing('report-exports/expired.xlsx');
    expect(ReportExport::query()->whereKey($expired->id)->exists())->toBeFalse();

    Storage::disk('local')->assertExists('report-exports/still-valid.xlsx');
    expect(ReportExport::query()->whereKey($stillValid->id)->exists())->toBeTrue();
});
