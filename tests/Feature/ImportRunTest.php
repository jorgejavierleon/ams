<?php

use App\Enums\ImportRunStatus;
use App\Enums\ImportStrategy;
use App\Models\ImportRun;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an ImportRun casts its enums and counters, defaulting counts to zero', function () {
    $organization = Organization::factory()->create();

    $importRun = ImportRun::factory()->create([
        'organization_id' => $organization->id,
        'strategy' => ImportStrategy::UpdateOnly,
        'match_key' => 'rut',
        'preview_counts' => ['ready' => 3, 'warning' => 0, 'error' => 1, 'skipped' => 0],
    ]);

    expect($importRun->status)->toBe(ImportRunStatus::Pending)
        ->and($importRun->strategy)->toBe(ImportStrategy::UpdateOnly)
        ->and($importRun->preview_counts)->toBe(['ready' => 3, 'warning' => 0, 'error' => 1, 'skipped' => 0])
        ->and($importRun->created_count)->toBe(0)
        ->and($importRun->updated_count)->toBe(0)
        ->and($importRun->skipped_count)->toBe(0)
        ->and($importRun->errored_count)->toBe(0);
});

test('an ImportRun is scoped to its organization', function () {
    $mine = Organization::factory()->create();
    $theirs = Organization::factory()->create();

    ImportRun::factory()->create(['organization_id' => $mine->id]);
    ImportRun::factory()->create(['organization_id' => $theirs->id]);

    session(['organization_id' => $mine->id]);

    expect(ImportRun::count())->toBe(1);
});
