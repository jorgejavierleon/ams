<?php

namespace App\Models;

use App\Enums\DocumentSignatureStatus;
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
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

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
class Document extends Model implements HasMedia
{
    /** @use HasFactory<DocumentFactory> */
    use BelongsToOrganization, HasFactory, InteractsWithMedia;

    /**
     * Media collection holding the final, fully-signed PDF (body plus the
     * "firmas electrónicas simples" block). Generated once every signatory has
     * signed and kept as the authoritative signed artifact.
     */
    public const SIGNED_MEDIA_COLLECTION = 'signed';

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::SIGNED_MEDIA_COLLECTION)->singleFile();
    }

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

    /**
     * A SHA-256 checksum of the exact (frozen) content a signatory consents to
     * when signing. The body is frozen at publish, so every signatory commits
     * to an identical hash, and any later tampering with the stored body is
     * detectable — the integrity guarantee Ley 19.799 (art. 2) expects of the
     * signed instrument.
     */
    public function contentHash(): string
    {
        return hash('sha256', (string) $this->body);
    }

    /**
     * The authenticated signatory's own signature that is currently actionable:
     * it must be pending and, under ordered signing, every lower-order
     * signature must already be signed. Returns null when the user has no
     * pending signature or it is not yet their turn.
     */
    public function actionableSignatureFor(User $user): ?DocumentSignature
    {
        $signature = $this->signatures()
            ->where('user_id', $user->id)
            ->where('status', DocumentSignatureStatus::Pending)
            ->first();

        if (! $signature instanceof DocumentSignature) {
            return null;
        }

        if ($this->ordered_signing && $signature->order !== null) {
            $blocked = $this->signatures()
                ->where('status', DocumentSignatureStatus::Pending)
                ->where('order', '<', $signature->order)
                ->exists();

            if ($blocked) {
                return null;
            }
        }

        return $signature;
    }
}
