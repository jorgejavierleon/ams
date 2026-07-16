<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesTableSort;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentVar;
use App\Models\User;
use App\Observers\DocumentObserver;
use App\Services\Documents\DocumentVariableResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    use ResolvesTableSort;

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value() ?: null;
        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['title', 'status', 'type', 'published_at', 'signed_at', 'created_at'],
            'created_at',
            'desc',
        );

        $status = $this->enumFilter($request, 'status', DocumentStatus::class);
        $type = $this->enumFilter($request, 'type', DocumentType::class);
        $employeeId = $request->integer('employee') ?: null;
        $from = $request->date('from');
        $to = $request->date('to');

        $documents = Document::query()
            ->with('user:id,name')
            ->when($search, fn ($query) => $query->where('title', 'like', "%{$search}%"))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($employeeId, fn ($query) => $query->where('user_id', $employeeId))
            ->when($from, fn ($query) => $query->whereDate('published_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('published_at', '<=', $to))
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('documents/index', [
            'documents' => $documents->through(fn (Document $document) => [
                'id' => $document->id,
                'title' => $document->title,
                'type' => $document->type?->label(),
                'employee' => $document->user?->name,
                'status' => [
                    'value' => $document->status->value,
                    'label' => $document->status->label(),
                    'variant' => $document->status->badgeVariant(),
                ],
                'published_at' => $document->published_at?->format('Y-m-d'),
                'signed_at' => $document->signed_at?->format('Y-m-d'),
            ]),
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                'status' => $status?->value,
                'type' => $type?->value,
                'employee' => $employeeId ? (string) $employeeId : null,
                'from' => $from?->format('Y-m-d'),
                'to' => $to?->format('Y-m-d'),
            ],
            'statusOptions' => DocumentStatus::options(),
            'typeOptions' => DocumentType::options(),
            'employeeOptions' => $this->employeeOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('documents/create', [
            'options' => $this->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateDocument($request);

        Document::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.documents.flash.created')]);

        return to_route('documents.index');
    }

    public function show(Document $document, DocumentVariableResolver $resolver): Response
    {
        $document->load('user:id,name');

        return Inertia::render('documents/show', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'type' => $document->type?->label(),
                'employee' => [
                    'id' => $document->user?->id,
                    'name' => $document->user?->name,
                ],
                'status' => [
                    'value' => $document->status->value,
                    'label' => $document->status->label(),
                    'variant' => $document->status->badgeVariant(),
                ],
                'legal_rep_signatories' => $document->legal_rep_signatories,
                'ordered_signing' => $document->ordered_signing,
                // Resolved so the preview shows the employee's real details even
                // while the document is still a draft holding raw placeholders.
                'body' => $resolver->resolve($document),
                'published_at' => $document->published_at?->format('Y-m-d'),
                'signed_at' => $document->signed_at?->format('Y-m-d'),
                'can_publish' => $document->status === DocumentStatus::Draft,
            ],
            // Signature status panel (#35) and activity timeline (#36) are
            // wired up by their own tickets; the sections render as placeholders.
            'signatures' => [],
            'activities' => [],
        ]);
    }

    public function edit(Document $document): Response
    {
        return Inertia::render('documents/edit', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'type' => $document->type?->value ?? '',
                'user_id' => (string) $document->user_id,
                'body' => $document->body ?? '',
                'legal_rep_signatories' => (string) $document->legal_rep_signatories,
                'ordered_signing' => $document->ordered_signing,
            ],
            'options' => $this->formOptions(),
        ]);
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        $data = $this->validateDocument($request);

        $document->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.documents.flash.updated')]);

        return to_route('documents.index');
    }

    public function destroy(Document $document): RedirectResponse
    {
        $document->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.documents.flash.deleted')]);

        return to_route('documents.index');
    }

    /**
     * Publish a draft document. The status transition drives the
     * {@see DocumentObserver}, which stamps `published_at` and
     * freezes the body by resolving its placeholders.
     */
    public function publish(Document $document): RedirectResponse
    {
        abort_unless($document->status === DocumentStatus::Draft, 403);

        $document->update(['status' => DocumentStatus::Published]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.documents.flash.published')]);

        return back();
    }

    /**
     * Resolve a value/label enum filter, ignoring unknown values.
     *
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T|null
     */
    private function enumFilter(Request $request, string $key, string $enum): ?\BackedEnum
    {
        $value = $request->string($key)->trim()->value();

        return $value === '' ? null : $enum::tryFrom($value);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateDocument(Request $request): array
    {
        $organizationId = Company::currentOrganizationId();

        $request->merge([
            'type' => $request->input('type') ?: null,
            'ordered_signing' => $request->boolean('ordered_signing'),
        ]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(DocumentType::class)],
            'user_id' => [
                'required', 'integer',
                Rule::exists('users', 'id')->where('organization_id', $organizationId),
            ],
            'body' => ['nullable', 'string'],
            'legal_rep_signatories' => ['required', 'integer', 'in:0,1,2'],
            'ordered_signing' => ['boolean'],
        ]);

        // Signing order is only meaningful with two legal-rep signatories.
        if ((int) $data['legal_rep_signatories'] < 2) {
            $data['ordered_signing'] = false;
        }

        return $data;
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
            'employees' => $this->employeeOptions(),
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

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function employeeOptions(): array
    {
        return User::query()
            ->employees()
            ->where('organization_id', Company::currentOrganizationId())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => ['value' => (string) $user->id, 'label' => $user->name])
            ->all();
    }
}
