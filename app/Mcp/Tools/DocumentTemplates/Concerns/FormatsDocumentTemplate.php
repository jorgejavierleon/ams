<?php

namespace App\Mcp\Tools\DocumentTemplates\Concerns;

use App\Models\DocumentTemplate;

/**
 * Shared structured-content shape for every document-template MCP tool's
 * response, so an agent sees the same fields regardless of which tool
 * produced the template.
 */
trait FormatsDocumentTemplate
{
    /**
     * @return array<string, mixed>
     */
    protected function formatDocumentTemplate(DocumentTemplate $documentTemplate): array
    {
        return [
            'id' => $documentTemplate->id,
            'title' => $documentTemplate->title,
            'type' => $documentTemplate->type?->value,
            'type_label' => $documentTemplate->type?->label(),
            'body' => $documentTemplate->body,
            'updated_at' => $documentTemplate->updated_at?->format('Y-m-d'),
        ];
    }
}
