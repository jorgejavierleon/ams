<?php

namespace App\Http\Controllers;

use App\Models\Position;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PositionController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value() ?: null;

        $positions = Position::query()
            ->withCount('activeUsers')
            ->when($search, fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('positions/index', [
            'positions' => $positions->through(fn (Position $position) => [
                'id' => $position->id,
                'name' => $position->name,
                'active_users_count' => $position->active_users_count,
            ]),
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Position::create($this->validatePosition($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.positions.flash.created')]);

        return to_route('positions.index');
    }

    public function show(Position $position): Response
    {
        $employees = $position->users()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(10);

        return Inertia::render('positions/show', [
            'position' => [
                'id' => $position->id,
                'name' => $position->name,
                'active_users_count' => $position->activeUsers()->count(),
            ],
            'employees' => $employees->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
            ]),
        ]);
    }

    public function update(Request $request, Position $position): RedirectResponse
    {
        $position->update($this->validatePosition($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.positions.flash.updated')]);

        return to_route('positions.index');
    }

    public function destroy(Position $position): RedirectResponse
    {
        if ($position->activeUsers()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('ui.positions.flash.has_employees')]);

            return to_route('positions.index');
        }

        $position->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.positions.flash.deleted')]);

        return to_route('positions.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePosition(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);
    }
}
