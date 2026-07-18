<?php

namespace App\Actions\Documents;

use App\Enums\DocumentSignatureStatus;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentSignature;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Records a signatory's rejection of a document. A single rejection is terminal
 * for the whole document: the signatory's own signature is marked rejected
 * (capturing the act's evidence and the stated reason), every other still
 * pending signature is cancelled, and the document transitions to rejected.
 */
class RejectDocument
{
    public function handle(Document $document, User $signer, string $ip, ?string $userAgent, ?string $reason = null): void
    {
        $signature = $document->signatures()
            ->where('user_id', $signer->id)
            ->where('status', DocumentSignatureStatus::Pending)
            ->first();

        abort_unless($signature instanceof DocumentSignature, 403);

        DB::transaction(function () use ($document, $signature, $signer, $ip, $userAgent, $reason): void {
            $signature->update([
                'status' => DocumentSignatureStatus::Rejected,
                'signed_ip' => $ip,
                'signed_user_agent' => $userAgent,
                'rejection_reason' => $reason,
                'verification_code' => null,
                'verification_code_expires_at' => null,
            ]);

            // Any other outstanding signatures can no longer be collected once
            // the document is dead, so they are cancelled rather than left
            // pending.
            $document->signatures()
                ->where('id', '!=', $signature->id)
                ->where('status', DocumentSignatureStatus::Pending)
                ->update([
                    'status' => DocumentSignatureStatus::Cancelled,
                    'verification_code' => null,
                    'verification_code_expires_at' => null,
                ]);

            $document->update(['status' => DocumentStatus::Rejected]);

            activity()
                ->causedBy($signer)
                ->performedOn($document)
                ->event('signature_rejected')
                ->log(__('ui.documents.activity.events.signature_rejected.description', [
                    'name' => $signer->name,
                ]));
        });
    }
}
