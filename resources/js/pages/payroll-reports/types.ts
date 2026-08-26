export type ReportFacetOption = { value: string; label: string; count: number };

export type PayrollReportEmployee = {
    id: number;
    name: string;
    email: string;
    rut: string | null;
    position: string | null;
    premise: string | null;
    cost_center: string | null;
    contract_type_label: string | null;
};

export type ReportPeriodType = 'month' | 'first_fortnight' | 'second_fortnight';

/**
 * The bulk employee selection (KOL-19 AC #3): `selectAll: false` with an
 * empty `ids` is a deliberate, explicit "nothing selected" (AC #7) — never
 * "the whole company". Mirrors `App\Support\EmployeeSelection`.
 *
 * - `selectAll: true` — every employee matching the current filters, minus
 *   the excluded `ids`.
 * - `selectAll: false` — exactly the employees named in `ids`.
 */
export type EmployeeSelection = {
    selectAll: boolean;
    ids: number[];
};

export type PayrollReportFilters = {
    search: string | null;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    premises: string[];
    costCenters: string[];
    positions: string[];
    contractTypes: string[];
};

export type PayrollReportFilterOptions = {
    premises: ReportFacetOption[];
    positions: ReportFacetOption[];
    costCenters: ReportFacetOption[];
    contractTypes: ReportFacetOption[];
};
