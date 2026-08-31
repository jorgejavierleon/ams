<?php

namespace App\Http\Controllers\Api;

use App\Actions\Documents\RejectDocument;
use App\Actions\Documents\SendVerificationCode;
use App\Actions\Documents\SignDocument;
use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\My\DocumentController;
use App\Http\Resources\DocumentDetailResource;
use App\Http\Resources\DocumentResource;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Document;
use App\Models\User;
use App\Services\Documents\DocumentVariableResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The Documentos tab (kolvi-mobile KMO-42/KMO-43/KMO-44/KMO-45): the
 * employee's own non-draft documents, one document's resolved body, and the
 * sign/reject flows behind the reader's sticky Rechazar / Firmar documento
 * bar — those belonging to them or listing them as a signatory. Mirrors
 * {@see DocumentController}'s own index()/show()/sendCode()/sign()/reject()
 * exactly, ported to /api/v1 the way KOL-81 ported the leaves list.
 */
class DocumentsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
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

        // The app disables JsonResource's default "data" wrapping
        // (AppServiceProvider), but kolvi-mobile's documents-api.ts parses a
        // `{data: [...]}` envelope, so it is wrapped explicitly here.
        return response()->json(['data' => DocumentResource::collection($documents)]);
    }

    /**
     * The Documentos tab's reader (KMO-43): one document's resolved body plus
     * the signature state driving the sticky Rechazar / Firmar documento bar.
     * A bare object, like day-detail and punch-receipt's own /me/*
     * single-resource shape — never the list's `{data: [...]}` envelope.
     *
     * Route model binding already scopes the lookup to the current
     * organization ({@see BelongsToOrganization}), so an
     * id from another tenant 404s before authorization is even reached.
     * {@see DocumentController::authorizeAccess()} for the ownership/signatory
     * rule this mirrors.
     */
    public function show(Request $request, Document $document, DocumentVariableResolver $resolver): DocumentDetailResource
    {
        $this->authorizeAccess($request, $document);

        return new DocumentDetailResource($document, $resolver->resolve($document));
    }

    /**
     * Mint a short-lived signed URL for the document's authoritative signed
     * PDF (kolvi-mobile KMO-46): the app opens the result with
     * Linking.openURL, an external browser with no Sanctum bearer token and
     * no session, so the URL itself has to authorize the request that
     * follows — {@see pdfShow()} is guarded only by the signature.
     */
    public function pdfUrl(Request $request, Document $document): JsonResponse
    {
        $this->authorizeAccess($request, $document);

        if ($document->getFirstMedia(Document::SIGNED_MEDIA_COLLECTION) === null) {
            return response()->json([
                'message' => __('ui.documents.api.pdf_not_ready'),
                'code' => 'pdf_not_ready',
            ], 409);
        }

        $expiresAt = now()->addMinutes(5);

        return response()->json([
            'url' => URL::temporarySignedRoute('v1.me.documents.pdf', $expiresAt, ['document' => $document->id]),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Stream the authoritative signed PDF for a Laravel-signed URL minted by
     * {@see pdfUrl()}. Only the `signed` route middleware authorizes this —
     * no Sanctum, no permission gate, no fallback to any other auth check —
     * since the destination is the OS's own PDF handler, which cannot attach
     * a bearer token. Serves the same file
     * {@see DocumentController::download()} already
     * serves.
     */
    public function pdfShow(Document $document): BinaryFileResponse
    {
        $media = $document->getFirstMedia(Document::SIGNED_MEDIA_COLLECTION);

        abort_unless($media !== null, 404);

        return response()->file($media->getPath(), ['Content-Type' => 'application/pdf']);
    }

    /**
     * Issue (or re-issue) the verification code for the signatory's own
     * actionable signature (kolvi-mobile KMO-44), mirroring
     * {@see DocumentController::sendCode()} exactly. `sent` is false, with
     * nothing minted or emailed, when the signer has no actionable signature
     * right now — already signed, rejected, or not yet their turn under
     * ordered signing.
     */
    public function sendCode(Request $request, Document $document, SendVerificationCode $sendCode): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeAccess($request, $document);

        $sent = $sendCode->handle($document, $user, $request->boolean('resend'));

        $expiresAt = $sent
            ? $document->actionableSignatureFor($user)?->verification_code_expires_at?->format('Y-m-d H:i:s')
            : null;

        return response()->json([
            'sent' => $sent,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Author the signatory's firma electrónica simple (kolvi-mobile KMO-44),
     * mirroring {@see DocumentController::sign()} exactly. SignDocument
     * mutates $document in place when this was the last outstanding
     * signature, so its status here already reflects that without a second
     * fetch.
     */
    public function sign(Request $request, Document $document, SignDocument $signDocument): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeAccess($request, $document);

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $signDocument->handle(
            $document,
            $user,
            $validated['code'],
            (string) $request->ip(),
            $request->userAgent(),
        );

        $signature = $document->signatures()->where('user_id', $user->id)->first();

        return response()->json([
            'status' => $signature->status->value,
            'signed_at' => $signature->signed_at?->format('Y-m-d H:i:s'),
            'document_status' => $document->status->value,
        ]);
    }

    /**
     * Record the signatory's rejection of the document (kolvi-mobile KMO-45),
     * mirroring {@see DocumentController::reject()} exactly. RejectDocument
     * mutates $document in place, the same as SignDocument does for sign().
     */
    public function reject(Request $request, Document $document, RejectDocument $rejectDocument): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeAccess($request, $document);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $rejectDocument->handle(
            $document,
            $user,
            (string) $request->ip(),
            $request->userAgent(),
            $validated['reason'] ?? null,
        );

        $signature = $document->signatures()->where('user_id', $user->id)->first();

        return response()->json([
            'status' => $signature->status->value,
            'document_status' => $document->status->value,
        ]);
    }

    /**
     * A signatory may only reach a document that belongs to them or lists
     * them as a signatory, mirroring
     * {@see DocumentController::authorizeAccess()} exactly.
     */
    private function authorizeAccess(Request $request, Document $document): void
    {
        /** @var User $user */
        $user = $request->user();

        $isSignatory = $document->signatures()->where('user_id', $user->id)->exists();

        abort_unless($document->user_id === $user->id || $isSignatory, 403);
    }
}
