<?php

namespace App\Mcp\Tools\Documents;

use App\Enums\DocumentStatus;
use App\Mcp\Tools\AuthorizedTool;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Observers\DocumentObserver;
use App\Services\Documents\DocumentVariableResolver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * Generates a draft Document for an employee from a DocumentTemplate,
 * mirroring what the "Load Template" action plus Save does on the document
 * create form: the template's title/type/body are copied onto a new Draft
 * Document, with the body's {{variable}} placeholders left unresolved (they
 * are only frozen at publish, by {@see DocumentObserver}).
 *
 * Unlike the web flow, this tool also returns the variables resolved via
 * {@see DocumentVariableResolver} — the same on-the-fly preview
 * DocumentController::show renders for a draft — so the calling agent can see
 * what the document will read like without altering the stored draft or
 * touching the publish/signature flow.
 *
 * Gated on Create:Document (KOL-130) rather than the route-level role:admin
 * check documents.store alone relies on today.
 */
#[Name('generate-document')]
#[Description('Generate a draft document for an employee from an existing template. The document is created as a draft only — it is never published or sent for signature.')]
class GenerateDocumentTool extends AuthorizedTool
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'document_template_id' => $schema->integer()
                ->description('The id of the template to generate the document from.')
                ->required(),
            'user_id' => $schema->integer()
                ->description('The id of the employee this document is for.')
                ->required(),
            'title' => $schema->string()
                ->max(255)
                ->description('Optional title override. Defaults to the template\'s title.'),
        ];
    }

    public function handle(Request $request, DocumentVariableResolver $resolver): Response|ResponseFactory
    {
        if ($response = $this->authorize($request, 'create', Document::class)) {
            return $response;
        }

        $organizationId = Company::currentOrganizationId();

        $data = $request->validate([
            'document_template_id' => ['required', 'integer'],
            'user_id' => [
                'required', 'integer',
                Rule::exists('users', 'id')->where('organization_id', $organizationId),
            ],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $template = DocumentTemplate::find((int) $data['document_template_id']);

        if ($template === null) {
            return Response::error('Document template not found.');
        }

        $employee = User::query()->findOrFail((int) $data['user_id']);

        $document = Document::create([
            'user_id' => $employee->id,
            'title' => $data['title'] ?? $template->title,
            'type' => $template->type,
            'body' => $template->body,
            'status' => DocumentStatus::Draft,
            'legal_rep_signatories' => 0,
            'ordered_signing' => false,
        ]);

        return Response::structured([
            'id' => $document->id,
            'title' => $document->title,
            'type' => $document->type?->value,
            'employee_id' => $employee->id,
            'employee' => $employee->name,
            'status' => $document->status->value,
            'resolved_body_preview' => $resolver->resolve($document),
        ]);
    }
}
