<?php

namespace App\Http\Controllers\Dt;

use App\Actions\Documents\DownloadDocument;
use App\Concerns\ResolvesTableSort;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Scopes\OrganizationScope;
use App\Services\Documents\DocumentVariableResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Read-only list of employment documents for the Labor Department (DT) audit.
 *
 * Resolución 38 (Art. 38-40) requires inspectors to reach the employer's
 * electronic documents during an inspection. The list is constrained to the
 * audit session organization by {@see OrganizationScope} and offers no
 * create/edit/delete — only viewing and a PDF download.
 */
class DocumentController extends Controller
{
    use ResolvesTableSort;

    /**
     * List the audited employer's documents.
     */
    public function index(Request $request): Response
    {
        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['status', 'published_at', 'signed_at'],
            'published_at',
            'desc',
        );

        $documents = Document::query()
            ->with('user:id,name')
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('dt/documents/index', [
            'documents' => $documents->through(fn (Document $document) => [
                'id' => $document->id,
                'title' => $document->title,
                'type' => $document->type?->label(),
                'employee' => $document->user->name,
                'status' => [
                    'value' => $document->status->value,
                    'label' => $document->status->label(),
                    'variant' => $document->status->badgeVariant(),
                ],
                'published_at' => $document->published_at?->format('Y-m-d'),
                'signed_at' => $document->signed_at?->format('Y-m-d'),
            ]),
            'filters' => [
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    /**
     * Show a single document's details and resolved body preview.
     */
    public function show(Document $document, DocumentVariableResolver $resolver): Response
    {
        $document->load('user:id,name');

        return Inertia::render('dt/documents/show', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'type' => $document->type?->label(),
                'employee' => $document->user->name,
                'status' => [
                    'value' => $document->status->value,
                    'label' => $document->status->label(),
                    'variant' => $document->status->badgeVariant(),
                ],
                // Resolved so the preview shows the employee's real details even
                // while the document is still a draft holding raw placeholders.
                'body' => $resolver->resolve($document),
                'published_at' => $document->published_at?->format('Y-m-d'),
                'signed_at' => $document->signed_at?->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Stream the document as a PDF preview via {@see DownloadDocument}.
     */
    public function download(Document $document, DownloadDocument $downloadDocument): SymfonyResponse
    {
        return $downloadDocument->handle($document);
    }
}
