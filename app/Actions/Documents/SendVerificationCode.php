<?php

namespace App\Actions\Documents;

use App\Mail\DocumentSignatureVerificationCode;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Issues the one-time code a signatory enters to author their firma
 * electrónica simple. The code is generated for the signatory's currently
 * actionable signature (pending and, under ordered signing, their turn),
 * persisted with a short expiry, and emailed to their personal address.
 *
 * A fresh code is only minted when none is live, unless {@see resend()} forces
 * one — so repeatedly opening the signing page does not spam new codes.
 */
class SendVerificationCode
{
    public const EXPIRY_MINUTES = 15;

    public function handle(Document $document, User $signer, bool $force = false): bool
    {
        $signature = $document->actionableSignatureFor($signer);

        if ($signature === null) {
            return false;
        }

        $codeIsLive = $signature->verification_code !== null
            && $signature->verification_code_expires_at?->isFuture() === true;

        if ($codeIsLive && ! $force) {
            return true;
        }

        $code = (string) random_int(100000, 999999);

        $signature->update([
            'verification_code' => $code,
            'verification_code_expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
        ]);

        Mail::to($signer->personal_email ?? $signer->email)
            ->send(new DocumentSignatureVerificationCode($document, $code));

        return true;
    }

    /**
     * Re-issue a code on the signatory's explicit request, replacing any live
     * one.
     */
    public function resend(Document $document, User $signer): bool
    {
        return $this->handle($document, $signer, force: true);
    }
}
