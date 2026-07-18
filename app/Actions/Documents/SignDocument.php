<?php

namespace App\Actions\Documents;

use App\Enums\DocumentSignatureStatus;
use App\Enums\DocumentStatus;
use App\Mail\DocumentFullySigned;
use App\Models\Document;
use App\Models\User;
use App\Services\Documents\SignedDocumentPdfGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Applies a signatory's firma electrónica simple to a document. The signing act
 * is authored by a one-time verification code; on success the signature record
 * captures the FES evidence Ley 19.799 (art. 2) expects — signer identity (the
 * authenticated user), the moment of signing, their IP and user agent, and a
 * hash of the exact content consented to.
 *
 * When the signature is the last outstanding one, the document is marked
 * signed, the authoritative signed PDF is generated and stored, and the
 * employee is notified.
 */
class SignDocument
{
    public function __construct(
        private SignedDocumentPdfGenerator $pdfGenerator,
    ) {}

    /**
     * @throws ValidationException when it is not the signatory's turn or the
     *                             verification code is missing, wrong or expired
     */
    public function handle(Document $document, User $signer, string $verificationCode, string $ip, ?string $userAgent): void
    {
        $signature = $document->actionableSignatureFor($signer);

        if ($signature === null || ! $signature->verificationCodeMatches($verificationCode)) {
            throw ValidationException::withMessages([
                'code' => __('ui.documents.signatures.sign.invalid_code'),
            ]);
        }

        DB::transaction(function () use ($document, $signature, $signer, $ip, $userAgent): void {
            $signature->update([
                'status' => DocumentSignatureStatus::Signed,
                'signed_at' => now(),
                'signed_ip' => $ip,
                'signed_user_agent' => $userAgent,
                'signed_content_hash' => $document->contentHash(),
                'verification_code' => null,
                'verification_code_expires_at' => null,
            ]);

            activity()
                ->causedBy($signer)
                ->performedOn($document)
                ->event('signature_signed')
                ->log(__('ui.documents.activity.events.signature_signed.description', [
                    'name' => $signer->name,
                ]));

            $this->completeIfFullySigned($document);
        });
    }

    /**
     * Once no pending signatures remain, freeze the document as signed, store
     * the authoritative signed PDF and notify the employee.
     */
    private function completeIfFullySigned(Document $document): void
    {
        $stillPending = $document->signatures()
            ->where('status', DocumentSignatureStatus::Pending)
            ->exists();

        if ($stillPending) {
            return;
        }

        $document->update([
            'status' => DocumentStatus::Signed,
            'signed_at' => now(),
        ]);

        $document->media()->where('collection_name', Document::SIGNED_MEDIA_COLLECTION)->delete();
        $document->addMediaFromString($this->pdfGenerator->generate($document)->output())
            ->usingFileName(Str::slug($document->title).'.pdf')
            ->toMediaCollection(Document::SIGNED_MEDIA_COLLECTION);

        activity()
            ->performedOn($document)
            ->event('signed')
            ->log(__('ui.documents.activity.events.signed.description'));

        Mail::to($document->user->personal_email ?? $document->user->email)
            ->send(new DocumentFullySigned($document));
    }
}
