<?php

namespace App\Actions\Documents;

use App\Enums\DocumentStatus;
use App\Models\Document;

/**
 * Duplicates a document into a fresh draft so an admin can correct and re-issue
 * it. Offered for voided, rejected or signed documents — the terminal states an
 * admin would want to replace with a corrected version.
 *
 * The copy carries over the original's title (suffixed with "(copia)"), type,
 * employee, body and signature configuration, but starts life as a clean draft:
 * no signatures, no `published_at` / `signed_at`, and a fully re-editable body.
 * The admin lands on the new draft's edit form to make the correction and then
 * publishes it normally.
 */
class DuplicateDocument
{
    public function handle(Document $document): Document
    {
        abort_unless($document->status->canBeDuplicated(), 403);

        return Document::create([
            'user_id' => $document->user_id,
            'title' => __('ui.documents.duplicate.title_suffix', ['title' => $document->title]),
            'type' => $document->type,
            'body' => $document->body,
            'status' => DocumentStatus::Draft,
            'legal_rep_signatories' => $document->legal_rep_signatories,
            'ordered_signing' => $document->ordered_signing,
            'published_at' => null,
            'signed_at' => null,
        ]);
    }
}
