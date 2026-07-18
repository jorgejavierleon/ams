<?php

namespace App\Actions\Documents;

use App\Models\Document;
use App\Services\Documents\DocumentPdfGenerator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams a document as a PDF. Once every party has signed and the authoritative
 * signed PDF (body plus the "firmas electrónicas simples" evidence block) has
 * been stored, that artifact is served — so the download always reflects the
 * signatures. For any earlier status it renders the body on the fly: a draft
 * downloads with its variables resolved for preview, a published document
 * downloads its frozen body.
 */
class DownloadDocument
{
    public function __construct(
        private DocumentPdfGenerator $generator,
    ) {}

    public function handle(Document $document): Response
    {
        $filename = Str::slug($document->title) ?: 'document';

        $signedPdf = $document->getFirstMedia(Document::SIGNED_MEDIA_COLLECTION);

        if ($signedPdf !== null) {
            return response()->download($signedPdf->getPath(), "{$filename}.pdf");
        }

        return $this->generator->generate($document)->download("{$filename}.pdf");
    }
}
