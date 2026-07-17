<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
});

function activityAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

/**
 * Request the deferred activities prop as Inertia would on a partial reload.
 * Partial requests return raw JSON, so callers assert on `props.activities`.
 */
function fetchActivities(User $admin, Document $document): TestResponse
{
    $version = (string) app(HandleInertiaRequests::class)->version(request());

    return test()->actingAs($admin)->get(route('documents.show', $document), [
        'X-Inertia' => 'true',
        'X-Inertia-Partial-Component' => 'documents/show',
        'X-Inertia-Partial-Data' => 'activities',
        'X-Inertia-Version' => $version,
    ]);
}

test('publishing a signable document records published and signature-requested activities', function () {
    Notification::fake();

    $admin = activityAdmin();
    $employee = User::factory()->employee()->create([
        'organization_id' => $admin->organization_id,
        'name' => 'Juan Pérez',
    ]);
    User::factory()->create([
        'organization_id' => $admin->organization_id,
        'is_legal_rep' => true,
    ]);

    $document = Document::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
        'type' => DocumentType::Contracts,
        'legal_rep_signatories' => 1,
        'status' => DocumentStatus::Draft,
    ]);

    $this->actingAs($admin)->post(route('documents.publish', $document))->assertRedirect();

    $activities = Activity::forSubject($document)->get();

    // One publish event plus one signature request per signatory (employee + legal rep).
    expect($activities)->toHaveCount(3);

    $published = $activities->firstWhere('event', 'published');
    expect($published)->not->toBeNull()
        ->and($published->causer->is($admin))->toBeTrue()
        ->and($published->properties['old']['status'])->toBe(DocumentStatus::Draft->value)
        ->and($published->properties['attributes']['status'])->toBe(DocumentStatus::PendingSignature->value);

    expect($activities->where('event', 'signature_requested'))->toHaveCount(2);
});

test('the show page exposes the activity timeline as a deferred prop', function () {
    Notification::fake();

    $admin = activityAdmin();
    $employee = User::factory()->employee()->create([
        'organization_id' => $admin->organization_id,
        'name' => 'Juan Pérez',
    ]);

    $document = Document::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
        'type' => DocumentType::Contracts,
        'legal_rep_signatories' => 0,
        'status' => DocumentStatus::Draft,
    ]);

    $this->actingAs($admin)->post(route('documents.publish', $document));

    $response = fetchActivities($admin, $document)
        ->assertOk()
        ->assertJsonPath('component', 'documents/show')
        // Reverse chronological: the publish event is the most recent, followed
        // by the employee's signature request logged moments earlier.
        ->assertJsonCount(2, 'props.activities')
        ->assertJsonPath('props.activities.0.event', 'published')
        ->assertJsonPath('props.activities.0.causer', $admin->name)
        ->assertJsonPath('props.activities.1.event', 'signature_requested');

    $published = $response->json('props.activities.0');
    expect($published['title'])->not->toBe('')
        ->not->toContain('ui.documents')
        ->and($published['status_change'])->not->toBeNull();
});

test('a document with no activity yields an empty timeline', function () {
    $admin = activityAdmin();
    $document = Document::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => User::factory()->employee()->create(['organization_id' => $admin->organization_id])->id,
        'status' => DocumentStatus::Draft,
    ]);

    fetchActivities($admin, $document)
        ->assertOk()
        ->assertJsonPath('component', 'documents/show')
        ->assertJsonCount(0, 'props.activities');
});
