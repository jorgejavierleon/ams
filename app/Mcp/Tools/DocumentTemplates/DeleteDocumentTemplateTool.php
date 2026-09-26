<?php

namespace App\Mcp\Tools\DocumentTemplates;

use App\Mcp\Tools\AuthorizedTool;
use App\Models\DocumentTemplate;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * Mirrors DocumentTemplateController::destroy 1:1 (a soft delete), gated on
 * Delete:DocumentTemplate (KOL-130) instead of the route-level role:admin
 * check alone.
 */
#[Name('delete-document-template')]
#[Description('Soft-delete a document template.')]
class DeleteDocumentTemplateTool extends AuthorizedTool
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'document_template_id' => $schema->integer()
                ->description('The id of the template to delete.')
                ->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $data = $request->validate([
            'document_template_id' => ['required', 'integer'],
        ]);

        $documentTemplate = DocumentTemplate::find((int) $data['document_template_id']);

        if ($documentTemplate === null) {
            return Response::error('Document template not found.');
        }

        if ($response = $this->authorize($request, 'delete', $documentTemplate)) {
            return $response;
        }

        $documentTemplate->delete();

        return Response::structured(['id' => $documentTemplate->id, 'deleted' => true]);
    }
}
