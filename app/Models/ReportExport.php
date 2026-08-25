<?php

namespace App\Models;

use App\Enums\ReportExportStatus;
use App\Jobs\GenerateReportExport;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ReportExportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A report export queued because its selection was too large to return
 * synchronously (KOL-16). {@see GenerateReportExport} renders it in
 * the background and the requester is notified once it lands in Ready or
 * Failed.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property string $type
 * @property string $format
 * @property array{start: string, end: string, user_ids: list<int>} $filters
 * @property ReportExportStatus $status
 * @property string|null $disk_path
 * @property string|null $filename
 * @property string|null $failure_reason
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['organization_id', 'user_id', 'type', 'format', 'filters', 'status', 'disk_path', 'filename', 'failure_reason', 'expires_at'])]
class ReportExport extends Model
{
    /** @use HasFactory<ReportExportFactory> */
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'status' => ReportExportStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
