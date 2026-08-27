<?php

namespace App\Http\Controllers\Api;

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
 * The Documentos tab's list (kolvi-mobile KMO-42): the employee's own
 * non-draft documents — those belonging to them or listing them as a
 * signatory — with a status badge and the awaiting_me flag that drives the
 * pending-signature count and tab-bar badge. Mirrors
 * {@see DocumentController::index()}'s scope exactly, ported to /api/v1 the
 * way KOL-81 ported the leaves list.
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
        /** @var User $user */
        $user = $request->user();

        $isSignatory = $document->signatures()->where('user_id', $user->id)->exists();

        abort_unless($document->user_id === $user->id || $isSignatory, 403);

        return new DocumentDetailResource($document, $resolver->resolve($document));
    }
}
