<?php

namespace App\Mcp\Tools\DocumentTemplates;

use App\Enums\DocumentType;
use App\Mcp\Tools\AuthorizedTool;
use App\Mcp\Tools\DocumentTemplates\Concerns\FormatsDocumentTemplate;
use App\Models\DocumentTemplate;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * Mirrors DocumentTemplateController::update 1:1, gated on Update:DocumentTemplate
 * (KOL-130) instead of the route-level role:admin check alone.
 */
#[Name('update-document-template')]
#[Description('Update an existing document template\'s title, type, and body.')]
class UpdateDocumentTemplateTool extends AuthorizedTool
{
    use FormatsDocumentTemplate;

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'document_template_id' => $schema->integer()
                ->description('The id of the template to update.')
                ->required(),
            'title' => $schema->string()
                ->max(255)
                ->description('The template title.')
                ->required(),
            'type' => $schema->string()
                ->enum(array_map(fn (DocumentType $type): string => $type->value, DocumentType::cases()))
                ->description('The document type this template produces.'),
            'body' => $schema->string()
                ->description('Rich text body, optionally containing {{variable}} placeholders (e.g. {{employee_name}}).'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $data = $request->validate([
            'document_template_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(DocumentType::class)],
            'body' => ['nullable', 'string'],
        ]);

        $documentTemplate = DocumentTemplate::find((int) $data['document_template_id']);

        if ($documentTemplate === null) {
            return Response::error('Document template not found.');
        }

        if ($response = $this->authorize($request, 'update', $documentTemplate)) {
            return $response;
        }

        $documentTemplate->update([
            'title' => $data['title'],
            'type' => $data['type'] ?? null,
            'body' => $data['body'] ?? null,
        ]);

        return Response::structured($this->formatDocumentTemplate($documentTemplate));
    }
}
