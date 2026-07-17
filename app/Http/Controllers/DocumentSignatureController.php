<?php

namespace App\Http\Controllers;

use App\Enums\DocumentSignatureStatus;
use App\Models\DocumentSignature;
use App\Notifications\DocumentSignatureRequested;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class DocumentSignatureController extends Controller
{
    /**
     * Re-send the signing invitation for a still-pending signature. Signed or
     * rejected signatures are terminal, so only pending ones can be nudged; the
     * signatory receives the same {@see DocumentSignatureRequested} notification
     * dispatched at publish time.
     */
    public function resend(DocumentSignature $documentSignature): RedirectResponse
    {
        if ($documentSignature->status !== DocumentSignatureStatus::Pending) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('ui.documents.signatures.resend.not_pending')]);

            return back();
        }

        $documentSignature->user->notify(new DocumentSignatureRequested($documentSignature->document));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.documents.signatures.resend.sent')]);

        return back();
    }
}
