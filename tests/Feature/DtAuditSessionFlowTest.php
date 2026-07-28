<?php

use App\Models\Document;
use App\Models\Mark;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * End-to-end smoke coverage for the DT inspector audit session: login →
 * organization selector → scoped audit views → export, plus the isolation
 * guarantees that keep one employer's data out of another's audit (#54).
 */
uses()->group('dt', 'smoke');

beforeEach(function () {
    Mail::fake();
});

test('a freshly logged-in inspector is routed to the organization selector', function () {
    User::factory()->dtUser()->create([
        'email' => 'inspector@dt.gov.cl',
        'password' => Hash::make('secret123'),
    ]);

    // Real login, then follow the dashboard redirect: with no audit session yet
    // every scoped view bounces back to the organization selector.
    $this->post('/dt/login', [
        'email' => 'inspector@dt.gov.cl',
        'password' => 'secret123',
    ])->assertRedirect(route('dt.dashboard'));

    $this->assertAuthenticatedAs(User::first(), 'dt');

    $this->followingRedirects()
        ->get(route('dt.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('dt/select-organization'));
});

test('selecting an organization opens the audit session and lands on the dashboard', function () {
    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();

    $this->actingAs($inspector, 'dt')
        ->post(route('dt.organization.store'), ['organization_id' => $organization->id])
        ->assertRedirect(route('dt.dashboard'))
        ->assertSessionHas('dt_organization_id', $organization->id);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('dt/dashboard'));
});

test('the inspector can validate a real mark checksum during the audit', function () {
    $inspector = User::factory()->dtUser()->create();
    $employee = User::factory()->employee()->create(['name' => 'Juan Pérez']);
    $mark = Mark::factory()->create(['user_id' => $employee->id]);

    $this->actingAs($inspector, 'dt')
        ->get(route('dt.marks.validate'))
        ->assertOk();

    $this->actingAs($inspector, 'dt')
        ->post(route('dt.marks.validate.store'), ['checksum' => $mark->checksum])
        ->assertRedirect(route('dt.marks.validate'))
        ->assertSessionHas('mark', fn (array $flashed) => $flashed['employee_name'] === 'Juan Pérez'
            && $flashed['checksum'] === $mark->checksum,
        );
});

test('the documents list is scoped to the audited organization', function () {
    $inspector = User::factory()->dtUser()->create();
    $audited = Organization::factory()->create();
    $other = Organization::factory()->create();

    Document::factory()->for($audited)->create(['title' => 'Audited contract']);
    Document::factory()->for($other)->create(['title' => 'Other employer contract']);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $audited->id])
        ->get(route('dt.documents.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dt/documents/index')
            ->has('documents.data', 1)
            ->where('documents.data.0.title', 'Audited contract'),
        );
});

test('reports return data only for the audited organization', function () {
    $inspector = User::factory()->dtUser()->create();
    $audited = Organization::factory()->create();
    $other = Organization::factory()->create();

    $auditedWorker = User::factory()->for($audited)->employee()->create(['name' => 'Audited Worker']);
    $otherWorker = User::factory()->for($other)->employee()->create(['name' => 'Other Worker']);

    // Both workers have attendance on the same day; only the audited one may surface.
    Mark::factory()->for($audited)->create(['user_id' => $auditedWorker->id, 'date_time' => '2026-03-02 08:00:00']);
    Mark::factory()->for($other)->create(['user_id' => $otherWorker->id, 'date_time' => '2026-03-02 08:00:00']);

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $audited->id])
        ->get(route('dt.reports.attendance', ['start' => '2026-03-02', 'end' => '2026-03-02']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('report', 1)
            ->where('report.0.employee', fn (string $value) => str_contains($value, 'Audited Worker')),
        );
});

test('the inspector can export a report for the audited organization', function () {
    $inspector = User::factory()->dtUser()->create();
    $organization = Organization::factory()->create();

    $this->actingAs($inspector, 'dt')
        ->withSession(['dt_organization_id' => $organization->id])
        ->get(route('dt.reports.export', ['type' => 'attendance', 'format' => 'excel', 'start' => '2026-03-02', 'end' => '2026-03-02']))
        ->assertOk();
});

test('an inspector cannot reach the admin panel', function () {
    User::factory()->dtUser()->create([
        'email' => 'inspector@dt.gov.cl',
        'password' => Hash::make('secret123'),
    ]);

    // Log in for real so only the DT guard session is set. The admin panel lives
    // behind the web guard, so the inspector is unauthenticated there and bounces
    // to the web login rather than seeing any admin view.
    $this->post('/dt/login', [
        'email' => 'inspector@dt.gov.cl',
        'password' => 'secret123',
    ]);

    $this->get(route('roles.index'))->assertRedirect(route('login'));
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});
