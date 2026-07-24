<?php

namespace App\Observers;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\Documents\DocumentVariableResolver;
use Illuminate\Support\Carbon;

class DocumentObserver
{
    public function __construct(
        private DocumentVariableResolver $resolver,
    ) {}

    /**
     * React to a document's status transition into "published": stamp the
     * publish date and freeze the body by resolving its `{{variable}}`
     * placeholders against the employee's data. Editing a document without
     * transitioning its status leaves the body untouched.
     */
    public function saving(Document $document): void
    {
        if (! $document->isDirty('status') || $document->status !== DocumentStatus::Published) {
            return;
        }

        if ($document->published_at === null) {
            $document->published_at = Carbon::now();
        }

        $document->body = $this->resolver->resolve($document);
    }
}
