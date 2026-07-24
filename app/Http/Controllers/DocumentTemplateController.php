<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesTableSort;
use App\Enums\DocumentType;
use App\Models\DocumentTemplate;
use App\Models\DocumentVar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DocumentTemplateController extends Controller
{
    use ResolvesTableSort;

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value() ?: null;
        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['title', 'type', 'updated_at'],
            'updated_at',
            'desc',
        );

        $variableKeys = DocumentVar::query()->pluck('key');

        $templates = DocumentTemplate::query()
            ->withTrashed()
            ->when($search, fn ($query) => $query->where('title', 'like', "%{$search}%"))
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('document-templates/index', [
            'templates' => $templates->through(fn (DocumentTemplate $template) => [
                'id' => $template->id,
                'title' => $template->title,
                'type' => $template->type?->label(),
                'variable_count' => $this->countVariables($template->body, $variableKeys),
                'updated_at' => $template->updated_at?->format('Y-m-d'),
                'trashed' => $template->trashed(),
            ]),
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('document-templates/create', [
            'options' => $this->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        DocumentTemplate::create($this->validateTemplate($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.document_templates.flash.created')]);

        return to_route('document-templates.index');
    }

    public function edit(DocumentTemplate $documentTemplate): Response
    {
        return Inertia::render('document-templates/edit', [
            'template' => [
                'id' => $documentTemplate->id,
                'title' => $documentTemplate->title,
                'type' => $documentTemplate->type->value ?? '',
                'body' => $documentTemplate->body ?? '',
            ],
            'options' => $this->formOptions(),
        ]);
    }

    public function update(Request $request, DocumentTemplate $documentTemplate): RedirectResponse
    {
        $documentTemplate->update($this->validateTemplate($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.document_templates.flash.updated')]);

        return to_route('document-templates.index');
    }

    public function destroy(DocumentTemplate $documentTemplate): RedirectResponse
    {
        $documentTemplate->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.document_templates.flash.deleted')]);

        return to_route('document-templates.index');
    }

    /**
     * Restore a soft-deleted template. Resolving through `withTrashed()` keeps
     * the organization scope applied, so a template can only be restored from
     * within its own tenant.
     */
    public function restore(int $documentTemplate): RedirectResponse
    {
        $template = DocumentTemplate::withTrashed()->findOrFail($documentTemplate);

        $template->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.document_templates.flash.restored')]);

        return to_route('document-templates.index');
    }

    /**
     * Return the raw template body for the document form's "Load Template"
     * action, which drops it into the rich editor.
     */
    public function body(DocumentTemplate $documentTemplate): JsonResponse
    {
        return response()->json(['body' => $documentTemplate->body ?? '']);
    }

    /**
     * Count how many distinct global variables appear in the template body.
     *
     * @param  Collection<int, string>  $variableKeys
     */
    private function countVariables(?string $body, Collection $variableKeys): int
    {
        if ($body === null || $body === '') {
            return 0;
        }

        return $variableKeys
            ->filter(fn (string $key) => str_contains($body, $key))
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTemplate(Request $request): array
    {
        $request->merge(['type' => $request->input('type') ?: null]);

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(DocumentType::class)],
            'body' => ['nullable', 'string'],
        ]);
    }

    /**
     * Options shared by the create and edit forms.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'types' => DocumentType::options(),
            'variables' => DocumentVar::query()
                ->orderBy('name')
                ->get(['id', 'name', 'key', 'description'])
                ->map(fn (DocumentVar $var) => [
                    'id' => $var->id,
                    'name' => $var->name,
                    'key' => $var->key,
                    'description' => $var->description,
                ])
                ->all(),
        ];
    }
}
