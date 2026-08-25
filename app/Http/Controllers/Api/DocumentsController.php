<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\My\DocumentController;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\User;
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
}
