<?php

namespace App\Actions\Documents;

use App\Enums\DocumentStatus;
use App\Models\Document;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Voids (withdraws) a published document. A published document's body is frozen
 * and signed against, so it can never be edited or deleted in place; when an
 * admin spots an error the supported correction is to void the erroneous
 * document and re-issue a corrected one ({@see DuplicateDocument}).
 *
 * Voiding cancels every outstanding pending signature so the document can no
 * longer be signed, and transitions it to the terminal "voided" status. The
 * voided document stays in the record as an immutable audit trail. Only "live"
 * documents (published or out for signature) can be voided — Signed and
 * Rejected documents are terminal.
 */
class VoidDocument
{
    public function __construct(
        private CancelPendingSignatures $cancelPendingSignatures,
    ) {}

    public function handle(Document $document): void
    {
        abort_unless($document->status->canBeVoided(), 403);

        DB::transaction(function () use ($document): void {
            $previousStatus = $document->status;

            $this->cancelPendingSignatures->handle($document);

            $document->update(['status' => DocumentStatus::Voided]);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($document)
                ->event('voided')
                ->withProperties([
                    'old' => ['status' => $previousStatus->value],
                    'attributes' => ['status' => DocumentStatus::Voided->value],
                ])
                ->log(__('ui.documents.activity.events.voided.description'));
        });
    }
}
