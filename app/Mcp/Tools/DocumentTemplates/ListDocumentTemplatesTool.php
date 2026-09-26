<?php

namespace App\Mcp\Tools\DocumentTemplates;

use App\Mcp\Tools\AuthorizedTool;
use App\Mcp\Tools\DocumentTemplates\Concerns\FormatsDocumentTemplate;
use App\Models\DocumentTemplate;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * Mirrors DocumentTemplateController::index 1:1 (minus pagination — an agent
 * gets the full list), gated on ViewAny:DocumentTemplate (KOL-130) instead of
 * the route-level role:admin check alone.
 */
#[Name('list-document-templates')]
#[Description('List the organization\'s document templates, for browsing or picking one to generate a document from.')]
class ListDocumentTemplatesTool extends AuthorizedTool
{
    use FormatsDocumentTemplate;

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($response = $this->authorize($request, 'viewAny', DocumentTemplate::class)) {
            return $response;
        }

        $templates = DocumentTemplate::query()
            ->orderBy('title')
            ->get();

        return Response::structured([
            'templates' => $templates->map(fn (DocumentTemplate $template) => $this->formatDocumentTemplate($template))->all(),
        ]);
    }
}
