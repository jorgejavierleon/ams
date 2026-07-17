<?php

namespace App\Actions\Documents;

use App\Models\Document;
use App\Services\Documents\DocumentPdfGenerator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams a document as a PDF. Available for documents in any status: a draft
 * downloads with its variables resolved for preview, while a published document
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

        return $this->generator->generate($document)->download("{$filename}.pdf");
    }
}
