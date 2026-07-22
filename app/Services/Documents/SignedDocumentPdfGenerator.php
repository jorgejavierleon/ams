<?php

namespace App\Services\Documents;

use App\Enums\DocumentSignatureStatus;
use App\Models\Document;
use App\Models\DocumentSignature;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * Renders the authoritative signed PDF for a fully-signed document: the frozen
 * body followed by a "firmas electrónicas simples" block that lists each
 * signatory with the FES evidence captured at signing — name, RUT, email,
 * timestamp and the content hash they committed to.
 */
class SignedDocumentPdfGenerator
{
    public function __construct(
        private DocumentVariableResolver $resolver,
    ) {}

    public function generate(Document $document): DomPdf
    {
        $signatures = $document->signatures()
            ->where('status', DocumentSignatureStatus::Signed)
            ->with('user:id,name,rut,personal_email')
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        return Pdf::loadView('documents.signed-pdf', [
            'title' => $document->title,
            'body' => $this->resolver->resolve($document),
            'signatures' => $signatures->map(fn (DocumentSignature $signature): array => [
                'name' => $signature->user?->name,
                'rut' => $signature->user?->formatted_rut,
                'email' => $signature->user?->personal_email,
                'type' => $signature->type->label(),
                'signed_at' => $signature->signed_at
                    ?->timezone(config('app.timezone_display', config('app.timezone')))
                    ->format('d/m/Y H:i:s'),
                'hash' => $signature->signed_content_hash,
            ])->all(),
        ])->setPaper('letter');
    }
}
