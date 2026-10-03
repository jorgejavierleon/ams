<?php

namespace App\Models;

use App\Listeners\RecordEmailSend;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\EmailSendFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per outgoing email actually sent (KOL-137.1), recorded by
 * {@see RecordEmailSend} off Laravel's MessageSent event.
 *
 * Deliberately not scoped by {@see BelongsToOrganization}:
 * the SaaS panel (no tenant context) must always see every organization's
 * volume, never silently filtered by whatever tenant the current session
 * happens to resolve to.
 *
 * @property int $id
 * @property int $organization_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 */
#[Fillable(['organization_id'])]
class EmailSend extends Model
{
    /** @use HasFactory<EmailSendFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
