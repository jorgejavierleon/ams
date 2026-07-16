<?php

namespace App\Models;

use App\Enums\DocumentSignatureStatus;
use App\Enums\DocumentSignatureType;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\DocumentSignatureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single required signature on a {@see Document}. The signing workflow that
 * populates and transitions these records is built by later tickets; this
 * model exists so the document's `signatures` relation resolves.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $document_id
 * @property int $user_id
 * @property DocumentSignatureType $type
 * @property DocumentSignatureStatus $status
 * @property int|null $order
 * @property Carbon|null $signed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['document_id', 'user_id', 'type', 'status', 'order', 'signed_at'])]
class DocumentSignature extends Model
{
    /** @use HasFactory<DocumentSignatureFactory> */
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'status' => DocumentSignatureStatus::class,
            'type' => DocumentSignatureType::class,
            'signed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
