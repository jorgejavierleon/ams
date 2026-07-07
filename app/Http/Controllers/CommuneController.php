<?php

namespace App\Http\Controllers;

use App\Models\Region;
use Illuminate\Http\JsonResponse;

class CommuneController extends Controller
{
    /**
     * List the communes belonging to a region, for the cascading select.
     */
    public function index(Region $region): JsonResponse
    {
        return response()->json(
            $region->communes()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($commune) => ['value' => $commune->id, 'label' => $commune->name]),
        );
    }
}
