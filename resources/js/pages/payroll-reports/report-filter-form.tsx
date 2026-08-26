import { useState } from 'react';
import type { Paginated } from '@/types/ui';
import { EmployeePicker } from './employee-picker';
import { PeriodSelector } from './period-selector';
import type {
    EmployeeSelection,
    PayrollReportEmployee,
    PayrollReportFilterOptions,
    PayrollReportFilters,
    ReportPeriodType,
} from './types';

type PeriodTypeOption = { value: ReportPeriodType; label: string };

type Props = {
    employees: Paginated<PayrollReportEmployee>;
    filters: PayrollReportFilters;
    filterOptions: PayrollReportFilterOptions;
    periodTypeOptions: PeriodTypeOption[];
    routeUrl: string;
};

const today = new Date();

/** `YYYY-MM`, the format `<input type="month">` uses. */
function toMonthValue(year: number, month: number): string {
    return `${year}-${String(month).padStart(2, '0')}`;
}

/**
 * Resolve `month` + `periodType` to a concrete date range, mirroring
 * `App\Support\ReportPeriod` so the preview shown here always matches what
 * the backend would compute for the same inputs.
 */
function resolvePeriodRange(
    monthValue: string,
    periodType: ReportPeriodType,
): { start: Date; end: Date } {
    const [year, month] = monthValue.split('-').map(Number);
    const lastDay = new Date(year, month, 0).getDate();

    if (periodType === 'first_fortnight') {
        return { start: new Date(year, month - 1, 1), end: new Date(year, month - 1, 15) };
    }

    if (periodType === 'second_fortnight') {
        return { start: new Date(year, month - 1, 16), end: new Date(year, month - 1, lastDay) };
    }

    return { start: new Date(year, month - 1, 1), end: new Date(year, month - 1, lastDay) };
}

/**
 * The shared payroll-report filter (RF-7, KOL-19): the period selector (a
 * month, split optionally into its first or second quincena — AC #2) plus
 * the employee picker. Every RF-1 report (KOL-20..24) will import this and
 * submit its resolved period + selection to the same aggregation service
 * (KOL-13) and integrity check (KOL-14) this ticket's backend already
 * targets. No report exists yet to submit to, so this renders as a live,
 * self-contained preview rather than a form with a destination.
 */
export function ReportFilterForm({
    employees,
    filters,
    filterOptions,
    periodTypeOptions,
    routeUrl,
}: Props) {
    const [month, setMonth] = useState(
        toMonthValue(today.getFullYear(), today.getMonth() + 1),
    );
    const [periodType, setPeriodType] = useState<ReportPeriodType>('month');
    const [selection, setSelection] = useState<EmployeeSelection>({
        selectAll: false,
        ids: [],
    });

    const { start, end } = resolvePeriodRange(month, periodType);

    return (
        <div className="space-y-6">
            <PeriodSelector
                month={month}
                periodType={periodType}
                periodTypeOptions={periodTypeOptions}
                start={start}
                end={end}
                onMonthChange={setMonth}
                onPeriodTypeChange={setPeriodType}
            />

            <EmployeePicker
                employees={employees}
                filters={filters}
                filterOptions={filterOptions}
                routeUrl={routeUrl}
                selection={selection}
                onSelectionChange={setSelection}
            />
        </div>
    );
}
