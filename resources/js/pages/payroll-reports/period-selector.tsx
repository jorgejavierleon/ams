import { ChevronLeft, ChevronRight, ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';

type Props = {
    month: string;
    onMonthChange: (month: string) => void;
};

const today = new Date();

function toMonth(year: number, monthIndex: number): string {
    return `${year}-${String(monthIndex + 1).padStart(2, '0')}`;
}

/** `YYYY-MM` for the current real month, the period picker's default. */
export function currentMonthValue(): string {
    return toMonth(today.getFullYear(), today.getMonth());
}

/**
 * The report filter's period picker (KOL-20 UI redesign): a single chip that
 * opens a month-grid dropdown, replacing the previous "Paso 1" card with
 * prev/next-month arrows and a quincena segmented control. Quincena support
 * was dropped from the UI along with it — `ReportPeriod`/`ReportPeriodType`
 * still resolve quincenas server-side, this picker just never offers one.
 */
export function PeriodSelector({ month, onMonthChange }: Props) {
    const { t, localeTag } = useTranslations();
    const [open, setOpen] = useState(false);
    const [year, monthNumber] = month.split('-').map(Number);
    const [viewYear, setViewYear] = useState(year);

    const rawMonthLabel = new Intl.DateTimeFormat(localeTag, {
        month: 'long',
        year: 'numeric',
    }).format(new Date(year, monthNumber - 1, 1));
    const monthLabel =
        rawMonthLabel.charAt(0).toUpperCase() + rawMonthLabel.slice(1);

    const monthFormatter = new Intl.DateTimeFormat(localeTag, {
        month: 'short',
    });

    const isFutureYear = viewYear >= today.getFullYear();

    return (
        <Popover
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (next) {
                    setViewYear(year);
                }
            }}
        >
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className={cn(
                        'inline-flex h-8 items-center gap-1.5 rounded-full border px-3 text-sm font-medium',
                        open
                            ? 'border-muted-foreground bg-muted'
                            : 'border-border bg-card hover:border-muted-foreground',
                    )}
                >
                    {monthLabel}
                    <ChevronDown className="size-3.5 text-muted-foreground" />
                </button>
            </PopoverTrigger>
            <PopoverContent className="w-68 p-2.5" align="end">
                <div className="flex items-center justify-between px-0.5 pb-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        aria-label={t(
                            'ui.payroll_reports.filters.period_prev_year',
                        )}
                        onClick={() => setViewYear((y) => y - 1)}
                    >
                        <ChevronLeft className="size-4" />
                    </Button>
                    <span className="text-sm font-semibold">{viewYear}</span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        aria-label={t(
                            'ui.payroll_reports.filters.period_next_year',
                        )}
                        disabled={isFutureYear}
                        onClick={() =>
                            setViewYear((y) => Math.min(today.getFullYear(), y + 1))
                        }
                    >
                        <ChevronRight className="size-4" />
                    </Button>
                </div>

                <div className="grid grid-cols-3 gap-1">
                    {Array.from({ length: 12 }, (_, monthIndex) => {
                        const future =
                            viewYear > today.getFullYear() ||
                            (viewYear === today.getFullYear() &&
                                monthIndex > today.getMonth());
                        const active =
                            viewYear === year && monthIndex === monthNumber - 1;

                        return (
                            <button
                                key={monthIndex}
                                type="button"
                                disabled={future}
                                onClick={() => {
                                    onMonthChange(toMonth(viewYear, monthIndex));
                                    setOpen(false);
                                }}
                                className={cn(
                                    'h-8 rounded-md text-sm capitalize',
                                    active
                                        ? 'bg-primary font-semibold text-primary-foreground'
                                        : 'text-foreground hover:bg-muted',
                                    future && 'cursor-default opacity-35 hover:bg-transparent',
                                )}
                            >
                                {monthFormatter.format(new Date(viewYear, monthIndex, 1))}
                            </button>
                        );
                    })}
                </div>
            </PopoverContent>
        </Popover>
    );
}
