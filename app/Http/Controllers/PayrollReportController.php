<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesTableSort;
use App\Enums\ContractType;
use App\Enums\ReportPeriodType;
use App\Models\User;
use App\Services\Reports\ReportEmployeeSelector;
use App\Support\ReportEmployeeFilters;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollReportController extends Controller
{
    use ResolvesTableSort;

    /**
     * The payroll reports section's landing page (KOL-18) plus the shared
     * employee-filter foundation every RF-1 report (KOL-20..24) will submit
     * a selection through (RF-7, KOL-19). The five reports still render as
     * "coming soon" until each lands. Distinct from the DT inspector's
     * compliance reports (`dt.reports.*`): this section is for tenant users
     * (RRHH/admin) and gated by `View:PayrollReport`, not the DT guard.
     */
    public function index(Request $request, ReportEmployeeSelector $selector): Response
    {
        $search = $request->string('search')->trim()->value() ?: null;
        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['name', 'email'],
            'name',
        );

        $filters = new ReportEmployeeFilters(
            premiseIds: $this->idListFilter($request, 'premises'),
            costCenterIds: $this->idListFilter($request, 'costCenters'),
            positionIds: $this->idListFilter($request, 'positions'),
            contractTypes: $this->enumListFilter($request, 'contractTypes', ContractType::class),
        );

        $employees = $selector->paginate($filters, $search, $sort, $direction);

        return Inertia::render('payroll-reports/index', [
            'reportTypes' => [
                'payroll-summary',
                'weekly-detail',
                'period-movements',
                'employee-master',
                'overtime-excess',
            ],
            'employees' => $employees->through(fn (User $employee): array => [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'rut' => $employee->formatted_rut,
                'position' => $employee->position?->name,
                'premise' => $employee->premise?->name,
                'cost_center' => $employee->costCenter?->name,
                'contract_type_label' => $employee->contract_type?->label(),
            ]),
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                'premises' => array_map('strval', $filters->premiseIds),
                'costCenters' => array_map('strval', $filters->costCenterIds),
                'positions' => array_map('strval', $filters->positionIds),
                'contractTypes' => array_map(fn (ContractType $type): string => $type->value, $filters->contractTypes),
            ],
            'filterOptions' => $selector->optionsFor(),
            'periodTypeOptions' => ReportPeriodType::options(),
        ]);
    }

    /**
     * Resolve a repeated id filter (e.g. `premises[]=1&premises[]=2`).
     *
     * @return list<int>
     */
    private function idListFilter(Request $request, string $key): array
    {
        return collect((array) $request->input($key, []))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Resolve a list of backed-enum cases from a repeated query parameter,
     * discarding any value that is not a valid case.
     *
     * @template TEnum of \BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @return list<TEnum>
     */
    private function enumListFilter(Request $request, string $key, string $enum): array
    {
        return collect((array) $request->input($key, []))
            ->map(fn ($value) => $enum::tryFrom((string) $value))
            ->filter()
            ->values()
            ->all();
    }
}
