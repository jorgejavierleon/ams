<?php

namespace App\Concerns;

use App\Enums\ContractType;
use App\Models\User;
use App\Support\ReportEmployeeFilters;
use Illuminate\Http\Request;

/**
 * Parses the RF-7 employee-filter dimensions (KOL-19) from the query string
 * into a {@see ReportEmployeeFilters}, shared by every controller that hosts
 * the employee picker (the payroll-reports landing page and each RF-1 report).
 */
trait ResolvesReportEmployeeFilters
{
    protected function reportEmployeeFilters(Request $request): ReportEmployeeFilters
    {
        return new ReportEmployeeFilters(
            premiseIds: $this->idListFilter($request, 'premises'),
            costCenterIds: $this->idListFilter($request, 'costCenters'),
            positionIds: $this->idListFilter($request, 'positions'),
            contractTypes: $this->enumListFilter($request, 'contractTypes', ContractType::class),
        );
    }

    /**
     * The `filters` Inertia prop shape the employee picker expects, echoing
     * the resolved filter dimensions back as strings.
     *
     * @return array{premises: list<string>, costCenters: list<string>, positions: list<string>, contractTypes: list<string>}
     */
    protected function reportEmployeeFiltersProp(ReportEmployeeFilters $filters): array
    {
        return [
            'premises' => array_map('strval', $filters->premiseIds),
            'costCenters' => array_map('strval', $filters->costCenterIds),
            'positions' => array_map('strval', $filters->positionIds),
            'contractTypes' => array_map(fn (ContractType $type): string => $type->value, $filters->contractTypes),
        ];
    }

    /**
     * The employee-picker row shape shared by the landing page and every
     * report's picker.
     *
     * @return array{id: int, name: string, email: string, rut: string|null, position: string|null, premise: string|null, cost_center: string|null, contract_type_label: string|null}
     */
    protected function payrollReportEmployeeRow(User $employee): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'rut' => $employee->formatted_rut,
            'position' => $employee->position?->name,
            'premise' => $employee->premise?->name,
            'cost_center' => $employee->costCenter?->name,
            'contract_type_label' => $employee->contract_type?->label(),
        ];
    }

    /**
     * Resolve a repeated id filter (e.g. `premises[]=1&premises[]=2`).
     *
     * @return list<int>
     */
    protected function idListFilter(Request $request, string $key): array
    {
        return array_values(array_filter(array_map(
            fn ($id): int => (int) $id,
            (array) $request->input($key, []),
        )));
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
    protected function enumListFilter(Request $request, string $key, string $enum): array
    {
        return array_values(array_filter(array_map(
            fn ($value) => $enum::tryFrom((string) $value),
            (array) $request->input($key, []),
        )));
    }
}
