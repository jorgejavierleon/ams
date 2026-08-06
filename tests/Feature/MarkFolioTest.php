<?php

use App\Models\Mark;
use App\Models\Organization;
use App\Models\User;
use App\Observers\MarkObserver;
use App\Support\Folio;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * A punch for the organization at the given wall-clock time, created through the
 * model so {@see MarkObserver} stamps the folio the way a real one
 * would.
 */
function punchAt(Organization $organization, string $dateTime): Mark
{
    return Mark::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => User::factory()->create(['organization_id' => $organization->id])->id,
        'date_time' => Carbon::parse($dateTime),
    ]);
}

// --- Format ---

test('a punch is stamped with a folio in the YYYYMMDD-NNNN form', function () {
    $mark = punchAt(Organization::factory()->create(), '2026-08-05 08:03:11');

    expect($mark->folio)->toMatch(Folio::PATTERN)
        ->and($mark->folio)->toBe('20260805-0001');
});

test('the folio carries the date of the punch, not the date it is read', function () {
    $organization = Organization::factory()->create();

    $this->travelTo(Carbon::parse('2026-08-06 10:00:00'));

    expect(punchAt($organization, '2026-08-05 23:50:00')->folio)->toStartWith('20260805-');
});

test('no mark reaches the register without a folio', function () {
    // The column is NOT NULL, so an insert that skips the observer is refused
    // outright rather than leaving a receipt Art. 13 cannot cover.
    expect(fn () => DB::table('marks')->insert([
        'organization_id' => Organization::factory()->create()->id,
        'date_time' => '2026-08-05 08:00:00',
        'type' => 'in',
        'checksum' => str_repeat('a', 64),
    ]))->toThrow(QueryException::class);
});

// --- The daily sequence ---

test('folios run consecutively within an organization on the same day', function () {
    $organization = Organization::factory()->create();

    $folios = collect(['08:00:00', '08:00:00', '12:30:00'])
        ->map(fn (string $time) => punchAt($organization, "2026-08-05 {$time}")->folio);

    // Two punches in the same second still take different numbers: the counter
    // is incremented under a lock, never read then written.
    expect($folios->all())->toBe(['20260805-0001', '20260805-0002', '20260805-0003']);
});

test('each organization keeps its own daily sequence', function () {
    $first = Organization::factory()->create();
    $second = Organization::factory()->create();

    punchAt($first, '2026-08-05 08:00:00');

    expect(punchAt($first, '2026-08-05 09:00:00')->folio)->toBe('20260805-0002')
        // The second organization is not affected by the first one's day.
        ->and(punchAt($second, '2026-08-05 09:05:00')->folio)->toBe('20260805-0001');
});

test('the sequence restarts on the next day', function () {
    $organization = Organization::factory()->create();

    punchAt($organization, '2026-08-05 08:00:00');
    punchAt($organization, '2026-08-05 18:00:00');

    expect(punchAt($organization, '2026-08-06 08:00:00')->folio)->toBe('20260806-0001');
});

test('a punch continues a sequence the register already holds for that day', function () {
    $organization = Organization::factory()->create();

    // What the backfill leaves behind for a day that already had punches: the
    // counter, not just the numbered marks. Without it the next punch would
    // reissue 0001 and collide.
    DB::table('mark_folios')->insert([
        'organization_id' => $organization->id,
        'folio_date' => '2026-08-05',
        'last_number' => 41,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(punchAt($organization, '2026-08-05 08:03:11')->folio)->toBe('20260805-0042');
});

// --- Uniqueness is the database's job ---

test('the database refuses two marks sharing a folio within an organization', function () {
    $mark = punchAt(Organization::factory()->create(), '2026-08-05 08:00:00');

    // The floor under the counter: were two concurrent allocations ever to
    // agree on a number, the second insert would fail rather than issue a
    // duplicate receipt number.
    expect(fn () => DB::table('marks')->insert([
        'organization_id' => $mark->organization_id,
        'date_time' => '2026-08-05 09:00:00',
        'type' => 'out',
        'checksum' => str_repeat('b', 64),
        'folio' => $mark->folio,
    ]))->toThrow(QueryException::class);
});

test('two organizations may legitimately hold the same folio', function () {
    $first = punchAt(Organization::factory()->create(), '2026-08-05 08:00:00');
    $second = punchAt(Organization::factory()->create(), '2026-08-05 08:00:00');

    expect($second->folio)->toBe($first->folio)
        ->and($second->organization_id)->not->toBe($first->organization_id);
});
