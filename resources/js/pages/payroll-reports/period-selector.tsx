import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import type { ReportPeriodType } from './types';

type PeriodTypeOption = { value: ReportPeriodType; label: string };

type Props = {
    month: string;
    periodType: ReportPeriodType;
    periodTypeOptions: PeriodTypeOption[];
    start: Date;
    end: Date;
    onMonthChange: (month: string) => void;
    onPeriodTypeChange: (periodType: ReportPeriodType) => void;
};

function shiftMonth(month: string, delta: number): string {
    const [year, monthNumber] = month.split('-').map(Number);
    const date = new Date(year, monthNumber - 1 + delta, 1);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

/**
 * The report filter's "Paso 1 · Período" panel (KOL-89): prev/next month
 * arrows plus a quincena segmented control, replacing the previous native
 * `<input type=month>` and `<Select>` pair. `report-filter-form.tsx` still
 * owns the month/periodType state and the resolved date range so the label
 * shown here can never disagree with the range caption below it.
 */
export function PeriodSelector({
    month,
    periodType,
    periodTypeOptions,
    start,
    end,
    onMonthChange,
    onPeriodTypeChange,
}: Props) {
    const { t, formatDate, localeTag } = useTranslations();

    const [year, monthNumber] = month.split('-').map(Number);
    const monthLabel = new Intl.DateTimeFormat(localeTag, {
        month: 'long',
        year: 'numeric',
    }).format(new Date(year, monthNumber - 1, 1));

    return (
        <div className="flex flex-wrap items-center justify-between gap-4 rounded-lg border bg-card p-4">
            <div className="flex flex-col gap-0.5">
                <span className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    {t('ui.payroll_reports.filters.step_period')}
                </span>
                <span className="text-base font-semibold capitalize">
                    {monthLabel}
                </span>
                <span className="text-sm text-muted-foreground">
                    {t('ui.payroll_reports.filters.period_range', {
                        start: formatDate(start),
                        end: formatDate(end),
                    })}
                </span>
            </div>

            <div className="flex flex-wrap items-center gap-3">
                <div className="flex items-center gap-1 rounded-lg border p-0.5">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label={t('ui.payroll_reports.filters.period_prev')}
                        onClick={() => onMonthChange(shiftMonth(month, -1))}
                    >
                        <ChevronLeft className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label={t('ui.payroll_reports.filters.period_next')}
                        onClick={() => onMonthChange(shiftMonth(month, 1))}
                    >
                        <ChevronRight className="size-4" />
                    </Button>
                </div>

                <div className="flex gap-1 rounded-lg bg-muted p-1">
                    {periodTypeOptions.map((option) => (
                        <button
                            key={option.value}
                            type="button"
                            onClick={() => onPeriodTypeChange(option.value)}
                            className={cn(
                                'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                periodType === option.value
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {t(
                                `ui.payroll_reports.filters.period_types_short.${option.value}`,
                            )}
                        </button>
                    ))}
                </div>
            </div>
        </div>
    );
}
