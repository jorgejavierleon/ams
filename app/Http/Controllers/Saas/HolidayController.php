<?php

namespace App\Http\Controllers\Saas;

use App\Actions\SyncOfficialHolidays;
use App\Concerns\ResolvesTableSort;
use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\Scopes\HolidayScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class HolidayController extends Controller
{
    use ResolvesTableSort;

    public function index(Request $request): Response
    {
        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['date', 'name', 'mandatory'],
            'date',
        );

        $holidays = Holiday::query()
            ->withoutGlobalScope(HolidayScope::class)
            ->whereNull('organization_id')
            ->orderBy($sort, $direction)
            ->get();

        return Inertia::render('saas/holidays/index', [
            // The official list is small and bounded, so it is shown in full.
            'holidays' => [
                'data' => $holidays->map(fn (Holiday $holiday) => [
                    'id' => $holiday->id,
                    'name' => $holiday->name,
                    'date' => $holiday->date->format('Y-m-d'),
                    'mandatory' => $holiday->mandatory,
                ])->values(),
            ],
            'filters' => ['sort' => $sort, 'direction' => $direction],
            'currentYear' => now()->year,
        ]);
    }

    public function sync(Request $request, SyncOfficialHolidays $sync): RedirectResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        try {
            $count = $sync->handle($data['year']);
        } catch (RuntimeException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('ui.saas_holidays.flash.failed')]);

            return to_route('saas.holidays.index');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.saas_holidays.flash.imported', ['count' => $count, 'year' => $data['year']]),
        ]);

        return to_route('saas.holidays.index');
    }
}
