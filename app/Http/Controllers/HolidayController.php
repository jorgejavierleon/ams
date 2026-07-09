<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesTableSort;
use App\Models\Holiday;
use App\Models\Scopes\HolidayScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HolidayController extends Controller
{
    use ResolvesTableSort;

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value() ?: null;
        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['date', 'name', 'mandatory'],
            'date',
        );

        $holidays = Holiday::query()
            ->when($search, fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy($sort, $direction)
            ->get();

        return Inertia::render('holidays/index', [
            // Holidays are a small, bounded set, so the list is shown in full
            // (no pagination).
            'holidays' => [
                'data' => $holidays->map(fn (Holiday $holiday) => [
                    'id' => $holiday->id,
                    'name' => $holiday->name,
                    'date' => $holiday->date->format('Y-m-d'),
                    'mandatory' => $holiday->mandatory,
                    // Official holidays are shared and cannot be edited by the tenant.
                    'is_official' => $holiday->isOfficial(),
                ])->values(),
            ],
            'filters' => ['search' => $search, 'sort' => $sort, 'direction' => $direction],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organizationId = HolidayScope::currentOrganizationId();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => [
                'required', 'date',
                Rule::unique('holidays', 'date')->where('organization_id', $organizationId),
            ],
            'mandatory' => ['required', 'boolean'],
        ]);

        Holiday::create([...$data, 'organization_id' => $organizationId]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.holidays.flash.created')]);

        return to_route('holidays.index');
    }

    public function update(Request $request, Holiday $holiday): RedirectResponse
    {
        abort_if($holiday->isOfficial(), 403);

        $holiday->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mandatory' => ['required', 'boolean'],
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.holidays.flash.updated')]);

        return to_route('holidays.index');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        abort_if($holiday->isOfficial(), 403);

        $holiday->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.holidays.flash.deleted')]);

        return to_route('holidays.index');
    }
}
