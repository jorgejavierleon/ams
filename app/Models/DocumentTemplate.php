<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\DocumentTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A reusable document body template (e.g. "Standard Employment Contract") that
 * admins load to pre-fill a new document. The `body` holds rich text with
 * `{{variable}}` placeholders drawn from the global {@see DocumentVar} set;
 * they stay unresolved here and are only rendered once the template is loaded
 * into a document and that document is published.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $title
 * @property DocumentType|null $type
 * @property string|null $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['title', 'type', 'body'])]
class DocumentTemplate extends Model
{
    /** @use HasFactory<DocumentTemplateFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
        ];
    }
}
