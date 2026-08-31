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
