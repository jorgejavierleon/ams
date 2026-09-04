<?php

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('the prune command deletes the file and row for every abandoned import run, leaving other runs alone', function (ImportRunStatus $status) {
    $organization = Organization::factory()->create();

    Storage::fake('local');
    Storage::disk('local')->put('import-runs/abandoned.csv', 'contents');
    Storage::disk('local')->put('import-runs/processing.csv', 'contents');
    Storage::disk('local')->put('import-runs/fresh.csv', 'contents');

    $abandoned = ImportRun::factory()->for($organization)->create([
        'status' => $status,
        'disk_path' => 'import-runs/abandoned.csv',
        'expires_at' => now()->subMinute(),
    ]);

    $stillProcessing = ImportRun::factory()->for($organization)->create([
        'status' => ImportRunStatus::Processing,
        'disk_path' => 'import-runs/processing.csv',
        'expires_at' => now()->subMinute(),
    ]);

    $notYetExpired = ImportRun::factory()->for($organization)->create([
        'status' => $status,
        'disk_path' => 'import-runs/fresh.csv',
        'expires_at' => now()->addDay(),
    ]);

    $this->artisan('import-runs:prune-abandoned')->assertSuccessful();

    Storage::disk('local')->assertMissing('import-runs/abandoned.csv');
    expect(ImportRun::query()->whereKey($abandoned->id)->exists())->toBeFalse();

    Storage::disk('local')->assertExists('import-runs/processing.csv');
    expect(ImportRun::query()->whereKey($stillProcessing->id)->exists())->toBeTrue();

    Storage::disk('local')->assertExists('import-runs/fresh.csv');
    expect(ImportRun::query()->whereKey($notYetExpired->id)->exists())->toBeTrue();
})->with([
    'Pending' => ImportRunStatus::Pending,
    'MappingReview' => ImportRunStatus::MappingReview,
    'PreviewReady' => ImportRunStatus::PreviewReady,
]);

test('the prune command leaves a stale Completed or Failed import run untouched', function (ImportRunStatus $status) {
    $organization = Organization::factory()->create();

    Storage::fake('local');
    Storage::disk('local')->put('import-runs/finished.csv', 'contents');

    $finished = ImportRun::factory()->for($organization)->create([
        'status' => $status,
        'disk_path' => 'import-runs/finished.csv',
        'expires_at' => now()->subMinute(),
    ]);

    $this->artisan('import-runs:prune-abandoned')->assertSuccessful();

    Storage::disk('local')->assertExists('import-runs/finished.csv');
    expect(ImportRun::query()->whereKey($finished->id)->exists())->toBeTrue();
})->with([
    'Completed' => ImportRunStatus::Completed,
    'Failed' => ImportRunStatus::Failed,
]);
