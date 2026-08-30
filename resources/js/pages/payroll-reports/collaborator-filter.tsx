import { router } from '@inertiajs/react';
import { Check, ChevronDown, Minus, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types/ui';
import { EmployeeAvatar } from './employee-avatar';
import type { EmployeeSelection, PayrollReportEmployee } from './types';

type Props = {
    employees: Paginated<PayrollReportEmployee>;
    routeUrl: string;
    extraParams: Record<string, string[] | undefined>;
    selection: EmployeeSelection;
    onSelectionChange: (selection: EmployeeSelection) => void;
};

type SelectionState = 'checked' | 'excluded' | 'plain';

function SelectionIndicator({
    state,
    onClick,
    label,
}: {
    state: SelectionState;
    onClick: () => void;
    label: string;
}) {
    if (state === 'checked') {
        return (
            <button
                type="button"
                onClick={onClick}
                aria-label={label}
                className="flex size-5 shrink-0 items-center justify-center rounded-[6px] bg-primary text-primary-foreground"
            >
                <Check className="size-3.5" />
            </button>
        );
    }

    if (state === 'excluded') {
        return (
            <button
                type="button"
                onClick={onClick}
                aria-label={label}
                className="flex size-5 shrink-0 items-center justify-center rounded-[6px] border border-dashed border-amber-600 text-amber-600 dark:border-amber-400 dark:text-amber-400"
            >
                <Minus className="size-3.5" />
            </button>
        );
    }

    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={label}
            className="size-5 shrink-0 rounded-[6px] border border-muted-foreground/40"
        />
    );
}

/**
 * The "Colaborador" filter chip (KOL-20 UI redesign): a searchable
 * multi-select dropdown replacing the DataTable-backed employee picker
 * (KOL-19/KOL-89). Selection still goes through the shared `EmployeeSelection`
 * {selectAll, ids} model — "select all matching filters" and the manual
 * include/exclude toggle both live in `report-filter-form.tsx`'s status bar,
 * this component only owns searching and toggling individual employees.
 */
export function CollaboratorFilter({
    employees,
    routeUrl,
    extraParams,
    selection,
    onSelectionChange,
}: Props) {
    const { t } = useTranslations();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');

    // An id can only be selected by clicking a row that was visible at the
    // time, so caching every list of employees we've seen (across searches)
    // is enough to always resolve a selected employee's name later, even
    // once a new search no longer includes them.
    const [nameById, setNameById] = useState<Record<number, string>>(() =>
        Object.fromEntries(
            employees.data.map((employee) => [employee.id, employee.name]),
        ),
    );

    if (
        employees.data.some((employee) => nameById[employee.id] !== employee.name)
    ) {
        setNameById((previous) => {
            const next = { ...previous };

            for (const employee of employees.data) {
                next[employee.id] = employee.name;
            }

            return next;
        });
    }

    const extraParamsKey = JSON.stringify(extraParams);

    useEffect(() => {
        const timeout = setTimeout(() => {
            router.get(
                routeUrl,
                { ...extraParams, search: query || undefined },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['employees', 'filters'],
                },
            );
        }, 300);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [query, routeUrl, extraParamsKey]);

    const isSelected = (id: number): boolean =>
        selection.selectAll ? !selection.ids.includes(id) : selection.ids.includes(id);

    const toggle = (id: number) => {
        if (selection.selectAll) {
            onSelectionChange({
                selectAll: true,
                ids: isSelected(id)
                    ? [...selection.ids, id]
                    : selection.ids.filter((excluded) => excluded !== id),
            });

            return;
        }

        onSelectionChange({
            selectAll: false,
            ids: isSelected(id)
                ? selection.ids.filter((included) => included !== id)
                : [...selection.ids, id],
        });
    };

    const selectedEmployees = useMemo(
        () =>
            selection.selectAll
                ? []
                : selection.ids.map((id) => ({
                      id,
                      name: nameById[id] ?? `#${id}`,
                  })),
        [selection, nameById],
    );

    const pillEmployees = selectedEmployees.slice(0, 2);
    const overflowCount = selectedEmployees.length - pillEmployees.length;

    const hasActiveSelection = selection.selectAll || selectedEmployees.length > 0;

    const chipLabel = selection.selectAll
        ? selection.ids.length > 0
            ? t('ui.payroll_reports.filters.status.all_subtitle_with_excluded', {
                  count: String(selection.ids.length),
              })
            : t('ui.payroll_reports.filters.collaborator_all')
        : selectedEmployees.length === 1
          ? selectedEmployees[0].name
          : t('ui.payroll_reports.filters.selected_count', {
                count: String(selectedEmployees.length),
            });

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className={cn(
                        'inline-flex h-8 max-w-64 items-center gap-1.5 rounded-full border bg-card px-3 text-sm font-medium hover:border-muted-foreground',
                        hasActiveSelection && 'border-muted-foreground bg-muted',
                    )}
                >
                    <span className="truncate">
                        {t('ui.payroll_reports.filters.collaborator')}
                        {hasActiveSelection ? `: ${chipLabel}` : ''}
                    </span>
                    <ChevronDown className="size-3.5 shrink-0 text-muted-foreground" />
                </button>
            </PopoverTrigger>
            <PopoverContent className="w-96 p-2.5" align="start">
                <div className="flex min-h-[38px] flex-wrap items-center gap-1.5 rounded-lg border bg-background px-2 py-1.5">
                    {pillEmployees.map((employee) => (
                        <span
                            key={employee.id}
                            className="inline-flex items-center gap-1.5 rounded-full bg-muted py-0.5 pr-1 pl-2 text-xs"
                        >
                            {employee.name}
                            <button
                                type="button"
                                onClick={() => toggle(employee.id)}
                                aria-label={t('ui.payroll_reports.filters.chips.remove')}
                                className="flex size-4 items-center justify-center rounded-full text-muted-foreground hover:bg-background hover:text-foreground"
                            >
                                <X className="size-3" />
                            </button>
                        </span>
                    ))}
                    {overflowCount > 0 && (
                        <span className="px-0.5 text-xs text-muted-foreground">
                            +{overflowCount}
                        </span>
                    )}
                    <input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder={t(
                            'ui.payroll_reports.filters.search_placeholder',
                        )}
                        className="h-6 min-w-24 flex-1 border-0 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                    />
                </div>

                <div className="flex items-center justify-between px-1 py-2 text-xs text-muted-foreground">
                    {t('ui.payroll_reports.filters.match_count', {
                        count: String(employees.total),
                    })}
                </div>

                <div className="flex max-h-60 flex-col gap-0.5 overflow-auto">
                    {employees.data.map((employee, index) => {
                        const state: SelectionState = isSelected(employee.id)
                            ? 'checked'
                            : selection.selectAll
                              ? 'excluded'
                              : 'plain';

                        return (
                            <button
                                key={employee.id}
                                type="button"
                                onClick={() => toggle(employee.id)}
                                className="flex w-full items-center gap-2.5 rounded-md p-1.5 text-left hover:bg-muted"
                            >
                                <SelectionIndicator
                                    state={state}
                                    onClick={() => toggle(employee.id)}
                                    label={t(
                                        'ui.payroll_reports.filters.select_employee',
                                    )}
                                />
                                <EmployeeAvatar
                                    name={employee.name}
                                    seed={index}
                                    className="size-7"
                                />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm font-medium">
                                        {employee.name}
                                    </span>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {[employee.rut, employee.premise]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </span>
                                </span>
                            </button>
                        );
                    })}
                    {employees.data.length === 0 && (
                        <div className="p-3 text-center text-xs text-muted-foreground">
                            {t('ui.payroll_reports.filters.empty')}
                        </div>
                    )}
                </div>
            </PopoverContent>
        </Popover>
    );
}
