<?php

namespace App\Http\Controllers\Saas;

use App\Concerns\ResolvesTableSort;
use App\Http\Controllers\Controller;
use App\Models\DocumentVar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DocumentVarController extends Controller
{
    use ResolvesTableSort;

    /**
     * A document variable key is the literal `{{snake_case}}` token embedded in
     * templates, stored with its surrounding braces.
     */
    private const KEY_FORMAT = '/^\{\{[a-z][a-z0-9_]*\}\}$/';

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value() ?: null;
        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['name', 'key', 'created_at'],
            'name',
        );

        $variables = DocumentVar::query()
            ->when($search, fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('key', 'like', "%{$search}%")))
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('saas/document-variables/index', [
            'variables' => $variables->through(fn (DocumentVar $variable) => [
                'id' => $variable->id,
                'name' => $variable->name,
                'key' => $variable->key,
                'description' => $variable->description,
                'created_at' => $variable->created_at?->toDateString(),
            ]),
            'filters' => ['search' => $search, 'sort' => $sort, 'direction' => $direction],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('saas/document-variables/create');
    }

    public function store(Request $request): RedirectResponse
    {
        DocumentVar::create($this->validateDocumentVar($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.document_variables.flash.created')]);

        return to_route('saas.document-variables.index');
    }

    public function edit(DocumentVar $documentVariable): Response
    {
        return Inertia::render('saas/document-variables/edit', [
            'variable' => [
                'id' => $documentVariable->id,
                'name' => $documentVariable->name,
                'key' => $documentVariable->key,
                'description' => $documentVariable->description,
            ],
        ]);
    }

    public function update(Request $request, DocumentVar $documentVariable): RedirectResponse
    {
        $documentVariable->update($this->validateDocumentVar($request, $documentVariable));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.document_variables.flash.updated')]);

        return to_route('saas.document-variables.index');
    }

    public function destroy(DocumentVar $documentVariable): RedirectResponse
    {
        $documentVariable->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.document_variables.flash.deleted')]);

        return to_route('saas.document-variables.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateDocumentVar(Request $request, ?DocumentVar $documentVariable = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'key' => [
                'required', 'string', 'max:255',
                'regex:'.self::KEY_FORMAT,
                Rule::unique('document_vars', 'key')->ignore($documentVariable),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [
            'key.regex' => __('ui.document_variables.validation.key_format'),
        ]);
    }
}
