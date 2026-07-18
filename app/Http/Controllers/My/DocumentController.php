<?php

namespace App\Http\Controllers\My;

use App\Actions\Documents\RejectDocument;
use App\Actions\Documents\SendVerificationCode;
use App\Actions\Documents\SignDocument;
use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\Documents\DocumentVariableResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Employee self-service documents: the "Mis documentos" panel where a
 * signatory reviews the documents published to them and authors their firma
 * electrónica simple. Every query is scoped to the authenticated user — a
 * document is visible only if it belongs to them or lists them as a signatory.
 */
class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $documents = Document::query()
            ->where('status', '!=', DocumentStatus::Draft)
            ->where(fn ($query) => $query
                ->where('user_id', $user->id)
                ->orWhereHas('signatures', fn ($signatures) => $signatures->where('user_id', $user->id)))
            ->with(['signatures' => fn ($query) => $query->where('user_id', $user->id)])
            ->latest('published_at')
            ->latest('id')
            ->get();

        return Inertia::render('my/documents/index', [
            'documents' => $documents->map(function (Document $document) use ($user): array {
                $mySignature = $document->signatures->first();

                return [
                    'id' => $document->id,
                    'title' => $document->title,
                    'type' => $document->type?->label(),
                    'status' => [
                        'value' => $document->status->value,
                        'label' => $document->status->label(),
                        'tone' => $document->status->badge(),
                    ],
                    'published_at' => $document->published_at?->format('Y-m-d'),
                    'my_signature' => $mySignature ? [
                        'status' => $mySignature->status->value,
                        'label' => $mySignature->status->label(),
                        'tone' => $mySignature->status->badge(),
                    ] : null,
                    'awaiting_me' => $document->actionableSignatureFor($user) !== null,
                ];
            })->all(),
        ]);
    }

    public function show(Request $request, Document $document, DocumentVariableResolver $resolver): Response
    {
        $user = $request->user();
        $this->authorizeAccess($request, $document);

        $mySignature = $document->signatures()->where('user_id', $user->id)->first();

        return Inertia::render('my/documents/show', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'type' => $document->type?->label(),
                'status' => [
                    'value' => $document->status->value,
                    'label' => $document->status->label(),
                    'tone' => $document->status->badge(),
                ],
                'body' => $resolver->resolve($document),
                'published_at' => $document->published_at?->format('Y-m-d'),
                'signed_at' => $document->signed_at?->format('Y-m-d'),
                'has_signed_pdf' => $document->getFirstMedia(Document::SIGNED_MEDIA_COLLECTION) !== null,
            ],
            'my_signature' => $mySignature ? [
                'status' => [
                    'value' => $mySignature->status->value,
                    'label' => $mySignature->status->label(),
                    'tone' => $mySignature->status->badge(),
                ],
                'signed_at' => $mySignature->signed_at?->format('Y-m-d H:i'),
                'can_sign' => $document->actionableSignatureFor($user) !== null,
            ] : null,
        ]);
    }

    /**
     * Issue (or re-issue) the verification code for the signatory's own
     * signature.
     */
    public function sendCode(Request $request, Document $document, SendVerificationCode $sendCode): RedirectResponse
    {
        $this->authorizeAccess($request, $document);

        $sent = $sendCode->resend($document, $request->user());

        Inertia::flash('toast', $sent
            ? ['type' => 'success', 'message' => __('ui.documents.signatures.sign.code_sent')]
            : ['type' => 'error', 'message' => __('ui.documents.signatures.sign.not_your_turn')]);

        return back();
    }

    public function sign(Request $request, Document $document, SignDocument $signDocument): RedirectResponse
    {
        $this->authorizeAccess($request, $document);

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $signDocument->handle(
            $document,
            $request->user(),
            $validated['code'],
            (string) $request->ip(),
            $request->userAgent(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.documents.signatures.sign.signed')]);

        return back();
    }

    public function reject(Request $request, Document $document, RejectDocument $rejectDocument): RedirectResponse
    {
        $this->authorizeAccess($request, $document);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $rejectDocument->handle(
            $document,
            $request->user(),
            (string) $request->ip(),
            $request->userAgent(),
            $validated['reason'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.documents.signatures.sign.rejected')]);

        return back();
    }

    /**
     * Download the authoritative signed PDF once the document is complete.
     */
    public function download(Request $request, Document $document): SymfonyResponse
    {
        $this->authorizeAccess($request, $document);

        $media = $document->getFirstMedia(Document::SIGNED_MEDIA_COLLECTION);

        abort_unless($media !== null, 404);

        return response()->download($media->getPath(), $media->file_name);
    }

    /**
     * A signatory may only reach a document that belongs to them or lists them
     * as a signatory.
     */
    private function authorizeAccess(Request $request, Document $document): void
    {
        $user = $request->user();

        $isSignatory = $document->signatures()
            ->where('user_id', $user->id)
            ->exists();

        abort_unless($document->user_id === $user->id || $isSignatory, 403);
    }
}
