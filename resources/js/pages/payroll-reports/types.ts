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

/** One employee's row in the "Resumen de Remuneraciones por Período" (KOL-20). */
export type PayrollSummaryReportRow = {
    userId: number;
    name: string;
    rut: string | null;
    workedHours: string;
    nonWorkedHours: string;
    totalLateness: string;
    overtimeOrdinaryDay: string;
    overtimeSundayOrHoliday: string;
    overtimeCompensatedInRestDays: string;
    overtimeUnauthorized: string;
    justifiedAbsenceDays: number;
    unjustifiedAbsenceDays: number;
    sundaysAndHolidaysWorked: number;
    paidWorkedDays: number;
    paidVacationDays: number;
    paidLeaveDays: number;
    nonPaidUnjustifiedAbsenceDays: number;
    nonPaidMedicalLeaveDays: number;
    nonPaidUnpaidLeaveDays: number;
};

export type PayrollSummaryReportTotal = Omit<PayrollSummaryReportRow, 'userId' | 'name' | 'rut'> & {
    employeeCount: number;
};

/** The single worker a "Detalle Semanal por Trabajador" (KOL-21) report covers. */
export type WeeklyDetailReportEmployee = {
    id: number;
    name: string;
    rut: string | null;
};

/** Real vs. teórica for one mark (entrada or salida), plus the already-computed difference. */
export type WeeklyDetailReportMarkTime = {
    real: string | null;
    theoretical: string | null;
    difference: string | null;
};

/** One day in the "Detalle Semanal por Trabajador" (KOL-21) grid. */
export type WeeklyDetailReportDay = {
    date: string;
    weekday_label: string;
    date_label: string;
    has_record: boolean;
    status: string | null;
    status_label: string | null;
    status_badge: string | null;
    entry: WeeklyDetailReportMarkTime;
    exit: WeeklyDetailReportMarkTime;
    lunch: {
        real: string | null;
        theoretical_start: string | null;
        theoretical_end: string | null;
    };
    leave: { type_label: string } | null;
    has_pending_modification: boolean;
    has_approved_modification: boolean;
};

export type WeeklyDetailReportWeek = {
    start: string;
    end: string;
    days: WeeklyDetailReportDay[];
};
