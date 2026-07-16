<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Concerns\BelongsToOrganization;
use App\Observers\DocumentObserver;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An employment document (contract, agreement or notification) drafted for a
 * single employee, published, and digitally signed. The `body` holds the rich
 * text with `{{variable}}` placeholders while it is a draft; on publish the
 * {@see DocumentObserver} freezes it by resolving the placeholders against the
 * employee's data and stamping `published_at`.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property string $title
 * @property DocumentType|null $type
 * @property string|null $body
 * @property DocumentStatus $status
 * @property int $legal_rep_signatories
 * @property bool $ordered_signing
 * @property Carbon|null $published_at
 * @property Carbon|null $signed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'title', 'type', 'body', 'status', 'legal_rep_signatories', 'ordered_signing', 'published_at', 'signed_at'])]
#[ObservedBy(DocumentObserver::class)]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'legal_rep_signatories' => 'integer',
            'ordered_signing' => 'boolean',
            'published_at' => 'datetime',
            'signed_at' => 'datetime',
        ];
    }

    /**
     * The employee the document belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The signatures collected for this document.
     *
     * @return HasMany<DocumentSignature, $this>
     */
    public function signatures(): HasMany
    {
        return $this->hasMany(DocumentSignature::class);
    }
}
