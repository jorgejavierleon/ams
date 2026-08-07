<?php

use App\Enums\MarkModificationReason;
use App\Enums\MarkModificationStatus;
use App\Enums\MarkType;
use App\Managers\MarkModificationManager;
use App\Models\Mark;
use App\Models\MarkModification;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workday;
use App\Notifications\MarkModificationRequested;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);
uses()->group('api');

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    // 12:00 UTC is 08:00 in Santiago, which is the wall clock every
    // `device_datetime` in this file is written against. Frozen so the 24 h cap
    // is measured against a known instant rather than against the clock the
    // suite happens to run on.
    $this->travelTo(Carbon::parse('2026-08-07 12:00:00', 'UTC'));
});

function offlineEmployee(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    return User::factory()->employee()->create([
        'organization_id' => $organization->id,
        'timezone' => 'America/Santiago',
    ]);
}

/**
 * The body the mobile client sends for a punch it queued while offline: the
 * ordinary punch keys plus the offline pair, which travel together or not at
 * all.
 *
 * @return array<string, mixed>
 */
function queuedPunchBody(string $type, string $deviceDateTime, ?string $idempotencyKey = null): array
{
    return [
        'type' => $type,
        'lat' => null,
        'lng' => null,
        'accuracy_m' => null,
        'geo_status' => 'unknown',
        'device_datetime' => $deviceDateTime,
        'idempotency_key' => $idempotencyKey ?? (string) Str::uuid(),
    ];
}

// --- A queued punch is recorded at the hour it was made ---

test('a queued punch is recorded at its device time, not at the sync time', function () {
    $employee = offlineEmployee();
    Sanctum::actingAs($employee);

    // Made at 06:30 in a basement, delivered at 08:00 when the signal returned.
    $this->postJson('/api/v1/marks', queuedPunchBody('IN', '2026-08-07 06:30:00'))
        ->assertCreated()
        ->assertJsonPath('datetime', '2026-08-07 06:30:00');

    // Res. 38 Art. 11: the sello de tiempo is the hour the marcación is made.
    // Stamping 08:00 would register a late arrival the employee did not commit.
    expect(Mark::first())
        ->date_time->format('Y-m-d H:i:s')->toBe('2026-08-07 06:30:00')
        ->original_date_time->format('Y-m-d H:i:s')->toBe('2026-08-07 06:30:00');
});

test('the raw device reading, the sync time and the provenance flag are all persisted', function () {
    Sanctum::actingAs(offlineEmployee());

    $this->postJson('/api/v1/marks', queuedPunchBody('IN', '2026-08-07 06:30:00'))->assertCreated();

    // The raw reading is kept beside the adjudicated legal value permanently, so
    // the two can always be compared.
    expect(Mark::first())
        ->device_datetime->format('Y-m-d H:i:s')->toBe('2026-08-07 06:30:00')
        ->synced_at->format('Y-m-d H:i:s')->toBe('2026-08-07 08:00:00')
        ->captured_offline->toBeTrue();
});

test('the receipt echoes the provenance so a synced comprobante can show it', function () {
    Sanctum::actingAs(offlineEmployee());

    $this->postJson('/api/v1/marks', queuedPunchBody('IN', '2026-08-07 06:30:00'))
        ->assertCreated()
        ->assertJsonPath('device_datetime', '2026-08-07 06:30:00')
        ->assertJsonPath('synced_at', '2026-08-07 08:00:00')
        ->assertJsonPath('captured_offline', true);
});

test('a queued punch still answers 409 on a day that already holds that type', function () {
    $employee = offlineEmployee();
    Sanctum::actingAs($employee);

    Mark::factory()->create([
        'user_id' => $employee->id,
        'organization_id' => $employee->organization_id,
        'type' => MarkType::In,
        'date_time' => '2026-08-06 09:00:00',
    ]);

    // Queued at 23:30 yesterday and synced this morning: it collides with
    // yesterday's punches, which is the day it was made, not with today's.
    $this->postJson('/api/v1/marks', queuedPunchBody('IN', '2026-08-06 23:30:00'))
        ->assertStatus(409)
        ->assertJsonPath('message', 'Ya registraste tu entrada de hoy.');

    expect(Mark::count())->toBe(1);
});

// --- The idempotency contract ---

test('a replay answers 200 with the original receipt rather than a second punch', function () {
    Sanctum::actingAs(offlineEmployee());

    $key = (string) Str::uuid();
    $body = queuedPunchBody('IN', '2026-08-07 06:30:00', $key);

    $first = $this->postJson('/api/v1/marks', $body)->assertCreated();
    $second = $this->postJson('/api/v1/marks', $body)->assertOk();

    // A retry whose answer was lost is not a second punch, and the employee may
    // not be able to tell the two responses apart.
    expect($second->getContent())->toBe($first->getContent())
        ->and(Mark::count())->toBe(1);
});

test('the idempotency key is persisted on the mark', function () {
    Sanctum::actingAs(offlineEmployee());

    $key = (string) Str::uuid();
    $this->postJson('/api/v1/marks', queuedPunchBody('IN', '2026-08-07 06:30:00', $key))->assertCreated();

    expect(Mark::first()->idempotency_key)->toBe($key);
});

test('a replay is answered before the one-per-day guard can refuse it', function () {
    Sanctum::actingAs(offlineEmployee());

    $body = queuedPunchBody('IN', '2026-08-07 06:30:00');

    $this->postJson('/api/v1/marks', $body)->assertCreated();

    // The punch this key names is already in the register; answering 409 would
    // tell the queue to drop a punch it never got a receipt for.
    $this->postJson('/api/v1/marks', $body)->assertOk();
});

test('the same key belonging to two employees is two punches', function () {
    $employee = offlineEmployee();
    $colleague = offlineEmployee($employee->organization);

    $key = (string) Str::uuid();
    $body = queuedPunchBody('IN', '2026-08-07 06:30:00', $key);

    Sanctum::actingAs($employee);
    $this->postJson('/api/v1/marks', $body)->assertCreated();

    // Keys are scoped per employee: one device's accidental collision must not
    // refuse somebody else's punch.
    Sanctum::actingAs($colleague);
    $this->postJson('/api/v1/marks', $body)->assertCreated();

    expect(Mark::count())->toBe(2);
});

test('the database refuses to record one employee key twice', function () {
    $employee = offlineEmployee();
    $key = (string) Str::uuid();

    $attributes = [
        'user_id' => $employee->id,
        'organization_id' => $employee->organization_id,
        'type' => MarkType::In,
        'idempotency_key' => $key,
    ];

    Mark::factory()->create([...$attributes, 'date_time' => '2026-08-07 06:30:00']);

    // The unique index is what makes the guarantee real rather than a race the
    // controller's lookup usually wins.
    expect(fn () => Mark::factory()->create([...$attributes, 'date_time' => '2026-08-07 07:30:00']))
        ->toThrow(QueryException::class);
});

// --- The window ---

test('a device clock running ahead is refused and records nothing', function () {
    Sanctum::actingAs(offlineEmployee());

    // 08:30 on a wall clock reading 08:00 — a punch cannot have been made in a
    // future the register has not reached (Art. 11).
    $this->postJson('/api/v1/marks', queuedPunchBody('IN', '2026-08-07 08:30:00'))
        ->assertStatus(422)
        ->assertJsonPath('code', 'queued_punch_in_future')
        ->assertJsonPath(
            'message',
            'La fecha y hora de tu teléfono está adelantada respecto del servidor, así que no podemos registrar esta marca. Ajusta la fecha y hora automáticas en tu dispositivo y vuelve a intentarlo.',
        );

    expect(Mark::count())->toBe(0)
        ->and(MarkModification::count())->toBe(0);
});

test('ordinary clock drift inside the tolerance still records the punch', function () {
    Sanctum::actingAs(offlineEmployee());

    // Two minutes ahead is a phone, not a forgery.
    $this->postJson('/api/v1/marks', queuedPunchBody('IN', '2026-08-07 08:02:00'))->assertCreated();

    expect(Mark::count())->toBe(1);
});

test('a punch just inside the 24 hour cap is still recorded', function () {
    Sanctum::actingAs(offlineEmployee());

    $this->postJson('/api/v1/marks', queuedPunchBody('IN', '2026-08-06 08:30:00'))
        ->assertCreated()
        ->assertJsonPath('datetime', '2026-08-06 08:30:00');
});

test('an over-age punch is filed as a pending addition instead of inserted', function () {
    Notification::fake();

    $employee = offlineEmployee();
    Sanctum::actingAs($employee);

    $this->postJson('/api/v1/marks', queuedPunchBody('IN', '2026-08-05 08:30:00'))
        ->assertStatus(422)
        ->assertJsonPath('code', 'queued_punch_too_old')
        ->assertJsonPath(
            'message',
            'Esta marca esperó más de 24 horas sin conexión, así que no podemos agregarla directamente al libro de asistencia. La enviamos a tu jefatura como marca faltante: recibirás un correo para revisarla y tienes 48 horas para responder.',
        );

    // Neither inserted nor discarded: past the cap the regulation's own
    // regularization machinery has already run, so the punch enters the record
    // through the Art. 39 b) addition pathway instead.
    expect(Mark::count())->toBe(0);

    $modification = MarkModification::first();

    expect($modification)->not->toBeNull()
        ->and($modification->status)->toBe(MarkModificationStatus::Pending)
        ->and($modification->reason)->toBe(MarkModificationReason::SystemError)
        ->and($modification->mark_type)->toBe(MarkType::In)
        ->and($modification->mark_id)->toBeNull()
        ->and($modification->user_id)->toBe($employee->id)
        ->and($modification->date_time->format('Y-m-d H:i:s'))->toBe('2026-08-05 08:30:00')
        ->and($modification->device_datetime->format('Y-m-d H:i:s'))->toBe('2026-08-05 08:30:00')
        ->and($modification->captured_offline)->toBeTrue();
});

test('filing an over-age punch notifies the employee so the 48 hour window can run', function () {
    Notification::fake();

    $employee = offlineEmployee();
    Sanctum::actingAs($employee);

    $this->postJson('/api/v1/marks', queuedPunchBody('IN', '2026-08-05 08:30:00'))->assertStatus(422);

    // Art. 40: the employee is told, and has 48 hours to object before silence
    // consolidates the addition.
    Notification::assertSentTo($employee, MarkModificationRequested::class);

    expect(MarkModification::first()->isActionable())->toBeTrue();
});

test('an over-age punch attaches to the day it was made, creating that day when it has none', function () {
    Notification::fake();

    $employee = offlineEmployee();
    Sanctum::actingAs($employee);

    $this->postJson('/api/v1/marks', queuedPunchBody('OUT', '2026-08-05 18:30:00'))->assertStatus(422);

    $workday = Workday::first();

    expect($workday)->not->toBeNull()
        ->and($workday->date->format('Y-m-d'))->toBe('2026-08-05')
        ->and($workday->user_id)->toBe($employee->id)
        ->and($workday->organization_id)->toBe($employee->organization_id)
        ->and(MarkModification::first()->workday_id)->toBe($workday->id);
});

test('retrying an over-age punch does not file it twice', function () {
    Notification::fake();

    Sanctum::actingAs(offlineEmployee());
    $body = queuedPunchBody('IN', '2026-08-05 08:30:00');

    $this->postJson('/api/v1/marks', $body)->assertStatus(422);
    $this->postJson('/api/v1/marks', $body)->assertStatus(422);

    expect(MarkModification::count())->toBe(1);
});

test('the mark a filed queued punch consolidates into is still flagged as captured offline', function () {
    Notification::fake();

    $employee = offlineEmployee();
    Sanctum::actingAs($employee);

    $this->postJson('/api/v1/marks', queuedPunchBody('IN', '2026-08-05 08:30:00'))->assertStatus(422);

    app(MarkModificationManager::class)->approve(MarkModification::first());

    // Art. 10's exception can only be `debidamente justificado` if the register
    // can say which of its marks were queued — including the ones that arrived
    // through the addition pathway rather than directly.
    expect(Mark::first())
        ->date_time->format('Y-m-d H:i:s')->toBe('2026-08-05 08:30:00')
        ->captured_offline->toBeTrue()
        ->device_datetime->format('Y-m-d H:i:s')->toBe('2026-08-05 08:30:00');
});

// --- The pair, and the timestamp that is still prohibited ---

test('a device_datetime without an idempotency key is rejected', function () {
    Sanctum::actingAs(offlineEmployee());

    $this->postJson('/api/v1/marks', [
        'type' => 'IN',
        'device_datetime' => '2026-08-07 06:30:00',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['idempotency_key']);

    expect(Mark::count())->toBe(0);
});

test('an idempotency key without a device_datetime is rejected', function () {
    Sanctum::actingAs(offlineEmployee());

    $this->postJson('/api/v1/marks', [
        'type' => 'IN',
        'idempotency_key' => (string) Str::uuid(),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['device_datetime']);

    expect(Mark::count())->toBe(0);
});

test('the device reading must be a naive wall clock, never an offset', function () {
    Sanctum::actingAs(offlineEmployee());

    // `2026-08-07T06:30:00-04:00` is a time re-read in whatever zone the reader
    // believes it is in, which is how a legal timestamp moves by an hour.
    $this->postJson('/api/v1/marks', queuedPunchBody('IN', '2026-08-07T06:30:00-04:00'))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['device_datetime']);
});

test('the idempotency key must be a v4 uuid', function () {
    Sanctum::actingAs(offlineEmployee());

    $this->postJson('/api/v1/marks', queuedPunchBody('IN', '2026-08-07 06:30:00', 'queued-punch-1'))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['idempotency_key']);
});

test('datetime stays prohibited on the queued path too', function () {
    Sanctum::actingAs(offlineEmployee());

    // `device_datetime` is evidence the server judges; `datetime` would be an
    // instruction it obeys, and the endpoint takes none.
    $this->postJson('/api/v1/marks', [
        ...queuedPunchBody('IN', '2026-08-07 06:30:00'),
        'datetime' => '2026-08-07 06:30:00',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['datetime']);

    expect(Mark::count())->toBe(0);
});

// --- An online punch is untouched ---

test('an online punch carries no provenance and syncs at the moment it is stamped', function () {
    Sanctum::actingAs(offlineEmployee());

    $this->postJson('/api/v1/marks', ['type' => 'IN'])
        ->assertCreated()
        ->assertJsonPath('device_datetime', null)
        ->assertJsonPath('captured_offline', false)
        ->assertJsonPath('synced_at', '2026-08-07 08:00:00');

    expect(Mark::first())
        ->captured_offline->toBeFalse()
        ->device_datetime->toBeNull()
        ->idempotency_key->toBeNull()
        ->date_time->format('Y-m-d H:i:s')->toBe('2026-08-07 08:00:00')
        ->synced_at->format('Y-m-d H:i:s')->toBe('2026-08-07 08:00:00');
});

// --- The Art. 8 checksum ---

test('an online punch hashes over exactly the string it always did', function () {
    $employee = offlineEmployee();
    Sanctum::actingAs($employee);

    $this->postJson('/api/v1/marks', ['type' => 'IN'])->assertCreated();

    $mark = Mark::first();

    // The formula is conditional, and this is the half that must never move:
    // every mark recorded before offline punching existed has to keep verifying.
    expect($mark->checksum)->toBe(
        hash('sha256', $employee->id.'in'.$mark->date_time->toIso8601String()),
    );
});

test('offline provenance is inside the checksum envelope', function () {
    $employee = offlineEmployee();
    Sanctum::actingAs($employee);

    $this->postJson('/api/v1/marks', queuedPunchBody('IN', '2026-08-07 06:30:00'))->assertCreated();

    $mark = Mark::first();

    expect($mark->checksum)->toBe(hash(
        'sha256',
        $employee->id.'in'.$mark->date_time->toIso8601String().'|offline|'.$mark->device_datetime->toIso8601String(),
    ));
});

test('clearing the offline flag invalidates the checksum', function () {
    $employee = offlineEmployee();
    Sanctum::actingAs($employee);

    $this->postJson('/api/v1/marks', queuedPunchBody('IN', '2026-08-07 06:30:00'))->assertCreated();

    $mark = Mark::first();

    // The point of bringing provenance inside the envelope: a mark quietly
    // relabelled as an ordinary punch no longer verifies against its own hash.
    $recomputed = hash('sha256', $employee->id.'in'.$mark->date_time->toIso8601String());

    expect($recomputed)->not->toBe($mark->checksum);
});
