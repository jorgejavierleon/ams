<?php

namespace App\Services\Documents;

use App\Models\Document;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * Renders a {@see Document} to a PDF by resolving its `{{variable}}`
 * placeholders (via {@see DocumentVariableResolver}) and laying the resulting
 * HTML body out through dompdf. Published documents already carry a frozen,
 * resolved body, so resolving again is a no-op; drafts are resolved on the fly
 * so the download always shows concrete data rather than raw tokens.
 */
class DocumentPdfGenerator
{
    public function __construct(
        private DocumentVariableResolver $resolver,
    ) {}

    public function generate(Document $document): DomPdf
    {
        return Pdf::loadView('documents.pdf', [
            'title' => $document->title,
            'body' => $this->resolver->resolve($document),
        ])->setPaper('letter');
    }
}
