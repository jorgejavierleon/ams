<?php

namespace App\Models;

use App\Enums\ImportRunStatus;
use App\Enums\ImportStrategy;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\BelongsToUser;
use Database\Factories\ImportRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A bulk data import (KOL-94): one uploaded file moving through mapping,
 * preview, and a queued commit, mirroring {@see ReportExport}'s
 * status-flip + queued-job pattern. Scoped to both the current organization
 * and the requesting user (KOL-105) so route-model-binding 404s a request
 * for another user's run in the same org, without every wizard route having
 * to check ownership itself.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property ImportRunStatus $status
 * @property array<int, array{sourceColumnIndex: int, sourceHeaderLabel: ?string, targetField: ?string, status: string}>|null $column_mapping
 * @property ImportStrategy|null $strategy
 * @property string|null $match_key
 * @property string|null $disk_path
 * @property string|null $original_filename
 * @property array{ready: int, warning: int, error: int, skipped: int}|null $preview_counts
 * @property int|null $committed_through
 * @property int $created_count
 * @property int $updated_count
 * @property int $skipped_count
 * @property int $errored_count
 * @property string|null $error_report_path
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['organization_id', 'user_id', 'status', 'column_mapping', 'strategy', 'match_key', 'disk_path', 'original_filename', 'preview_counts', 'committed_through', 'created_count', 'updated_count', 'skipped_count', 'errored_count', 'error_report_path', 'expires_at'])]
class ImportRun extends Model
{
    /** @use HasFactory<ImportRunFactory> */
    use BelongsToOrganization, BelongsToUser, HasFactory;

    /**
     * A new run always starts life at Pending with nothing committed yet.
     * Set here (not only as a migration column default) so a freshly
     * created instance reflects these values in memory immediately, without
     * needing a round-trip refresh from the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ImportRunStatus::Pending->value,
        'created_count' => 0,
        'updated_count' => 0,
        'skipped_count' => 0,
        'errored_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportRunStatus::class,
            'column_mapping' => 'array',
            'strategy' => ImportStrategy::class,
            'preview_counts' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
