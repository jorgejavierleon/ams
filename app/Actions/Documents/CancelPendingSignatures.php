<?php

namespace App\Actions\Documents;

use App\Enums\DocumentSignatureStatus;
use App\Models\Document;

/**
 * Cancels every still-pending signature on a document. Used whenever a document
 * dies before completion — a signatory rejects it or an admin voids it — so the
 * outstanding invitations can no longer be acted on. Any verification code in
 * flight is cleared with them.
 */
class CancelPendingSignatures
{
    public function handle(Document $document): void
    {
        $document->signatures()
            ->where('status', DocumentSignatureStatus::Pending)
            ->update([
                'status' => DocumentSignatureStatus::Cancelled,
                'verification_code' => null,
                'verification_code_expires_at' => null,
            ]);
    }
}
