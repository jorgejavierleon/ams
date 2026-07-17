<?php

namespace App\Actions\Documents;

use App\Enums\DocumentSignatureStatus;
use App\Enums\DocumentSignatureType;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use App\Notifications\DocumentSignatureRequested;

/**
 * Creates the pending signature records for a freshly published document and
 * notifies each signatory. Only documents whose type actually requires signing
 * (contracts, annexes and pacts) generate signatures; informational documents
 * stay in the "published" state.
 *
 * The signatory set is the employee the document belongs to plus the first
 * `legal_rep_signatories` legal representatives of the organization. When the
 * document uses ordered signing, signatories are numbered employee-first.
 */
class CreateDocumentSignatures
{
    public function handle(Document $document): void
    {
        if (! ($document->type?->requiresSignatureConfig() ?? false)) {
            return;
        }

        $signatories = $this->signatoriesFor($document);

        if ($signatories === []) {
            return;
        }

        foreach ($signatories as $signatory) {
            $signature = $document->signatures()->firstOrCreate(
                [
                    'user_id' => $signatory['user_id'],
                    'type' => $signatory['type'],
                ],
                [
                    'status' => DocumentSignatureStatus::Pending,
                    'order' => $signatory['order'],
                ],
            );

            $signature->user->notify(new DocumentSignatureRequested($document));
        }

        $document->update(['status' => DocumentStatus::PendingSignature]);
    }

    /**
     * Build the ordered list of signatories for the document.
     *
     * @return list<array{type: DocumentSignatureType, user_id: int, order: int|null}>
     */
    private function signatoriesFor(Document $document): array
    {
        $order = 1;

        // The employee always signs their own document.
        $signatories = [[
            'type' => DocumentSignatureType::Employee,
            'user_id' => $document->user_id,
            'order' => $document->ordered_signing ? $order++ : null,
        ]];

        $legalReps = User::query()
            ->where('organization_id', $document->organization_id)
            ->where('is_legal_rep', true)
            ->orderBy('id')
            ->limit($document->legal_rep_signatories)
            ->get();

        foreach ($legalReps as $legalRep) {
            $signatories[] = [
                'type' => DocumentSignatureType::LegalRep,
                'user_id' => $legalRep->id,
                'order' => $document->ordered_signing ? $order++ : null,
            ];
        }

        return $signatories;
    }
}
