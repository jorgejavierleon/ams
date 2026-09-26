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
 * Mirrors DocumentTemplateController::store 1:1, gated on Create:DocumentTemplate
 * (KOL-130) instead of the route-level role:admin check alone.
 */
#[Name('create-document-template')]
#[Description('Create a reusable document template with a title, optional type, and a body containing {{variable}} placeholders.')]
class CreateDocumentTemplateTool extends AuthorizedTool
{
    use FormatsDocumentTemplate;

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
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
        if ($response = $this->authorize($request, 'create', DocumentTemplate::class)) {
            return $response;
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(DocumentType::class)],
            'body' => ['nullable', 'string'],
        ]);

        $documentTemplate = DocumentTemplate::create($data);

        return Response::structured($this->formatDocumentTemplate($documentTemplate));
    }
}
