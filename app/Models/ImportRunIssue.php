<?php

namespace App\Models;

use App\Enums\ImportIssueSeverity;
use Database\Factories\ImportRunIssueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ImportIssue (KOL-94.3) persisted against its ImportRun so the preview
 * step's per-row table (KOL-111) can query/paginate what
 * PreviewImportRun's ephemeral ImportRow objects would otherwise discard.
 * Scoped implicitly through ImportRun's BelongsToOrganization/BelongsToUser
 * (KOL-105) — always query via `$importRun->issues()`, never
 * `ImportRunIssue::query()` directly, or that scope is bypassed.
 *
 * @property int $id
 * @property int $import_run_id
 * @property int $row_number
 * @property string|null $field
 * @property ImportIssueSeverity $severity
 * @property string $message
 */
#[Fillable(['import_run_id', 'row_number', 'field', 'severity', 'message'])]
class ImportRunIssue extends Model
{
    /** @use HasFactory<ImportRunIssueFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'severity' => ImportIssueSeverity::class,
        ];
    }

    /**
     * @return BelongsTo<ImportRun, $this>
     */
    public function importRun(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class);
    }
}
