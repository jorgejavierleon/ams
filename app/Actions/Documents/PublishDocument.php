<?php

namespace App\Actions\Documents;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Observers\DocumentObserver;
use Illuminate\Support\Facades\DB;

/**
 * Publishes a draft document: the status transition drives the
 * {@see DocumentObserver}, which stamps `published_at` and freezes the body by
 * resolving its `{{variable}}` placeholders. Signature records are then created
 * and their signatories notified.
 */
class PublishDocument
{
    public function __construct(
        private CreateDocumentSignatures $createSignatures,
    ) {}

    public function handle(Document $document): void
    {
        abort_unless($document->status === DocumentStatus::Draft, 403);

        DB::transaction(function () use ($document): void {
            $document->status = DocumentStatus::Published;
            $document->save();

            $this->createSignatures->handle($document);
        });
    }
}
