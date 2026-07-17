<?php

namespace App\Actions\Documents;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Observers\DocumentObserver;
use Illuminate\Support\Facades\Auth;
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
            $previousStatus = $document->status;

            $document->status = DocumentStatus::Published;
            $document->save();

            // Creating the signatures may transition a signable document on to
            // "pending signature", so the resulting status is read afterwards.
            $this->createSignatures->handle($document);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($document)
                ->event('published')
                ->withProperties([
                    'old' => ['status' => $previousStatus->value],
                    'attributes' => ['status' => $document->status->value],
                ])
                ->log(__('ui.documents.activity.events.published.description'));
        });
    }
}
