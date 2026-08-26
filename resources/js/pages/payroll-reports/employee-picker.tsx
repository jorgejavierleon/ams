import type { ColumnDef } from '@tanstack/react-table';
import { Check, Minus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types/ui';
import { ReportFacetFilter } from './report-facet-filter';
import type {
    EmployeeSelection,
    PayrollReportEmployee,
    PayrollReportFilterOptions,
    PayrollReportFilters,
    ReportFacetOption,
} from './types';

type Props = {
    employees: Paginated<PayrollReportEmployee>;
    filters: PayrollReportFilters;
    filterOptions: PayrollReportFilterOptions;
    routeUrl: string;
    selection: EmployeeSelection;
    onSelectionChange: (selection: EmployeeSelection) => void;
};

type SelectionState = 'checked' | 'excluded' | 'plain';

/** The row/header selection control's three visual states (KOL-89 AC #6). */
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
                className="flex size-5 items-center justify-center rounded-[6px] bg-primary text-primary-foreground"
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
                className="flex size-5 items-center justify-center rounded-[6px] border border-dashed border-amber-600 text-amber-600 dark:border-amber-400 dark:text-amber-400"
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
            className="size-5 rounded-[6px] border border-muted-foreground/40"
        />
    );
}

type FacetKey = 'premises' | 'positions' | 'costCenters' | 'contractTypes';

/**
 * The employee-picker table shared by every payroll report (RF-7, KOL-19).
 * Backed by the same server-driven `DataTable`/`useServerTable` foundation as
 * the Employees admin screen (AC #5) — search, sort and the facet filters all
 * round-trip to the server — but selection is tracked outside of
 * `DataTable`'s own per-page row-selection state, in the `selection` prop, so
 * the Talana "select all matching, then exclude a few" pattern survives both
 * pagination and filter changes (AC #3), which a page-scoped selection could
 * never do.
 */
export function EmployeePicker({
    employees,
    filters,
    filterOptions,
    routeUrl,
    selection,
    onSelectionChange,
}: Props) {
    const { t } = useTranslations();

    const [premises, setPremises] = useState<string[]>(filters.premises);
    const [positions, setPositions] = useState<string[]>(filters.positions);
    const [costCenters, setCostCenters] = useState<string[]>(
        filters.costCenters,
    );
    const [contractTypes, setContractTypes] = useState<string[]>(
        filters.contractTypes,
    );

    // Exclusion banner needs a name for every excluded id, but only the
    // current page of `employees` is loaded client-side. An id can only ever
    // be excluded by clicking a row that was visible at the time, so caching
    // every page we've seen is enough to always resolve a name later. Merged
    // during render (not in an effect) per React's "adjusting state while
    // rendering" pattern, since it only reacts to a prop identity change.
    const [seenPage, setSeenPage] = useState(employees.data);
    const [nameById, setNameById] = useState<Record<number, string>>(() =>
        Object.fromEntries(
            employees.data.map((employee) => [employee.id, employee.name]),
        ),
    );

    if (seenPage !== employees.data) {
        setSeenPage(employees.data);
        setNameById((previous) => {
            const next = { ...previous };

            for (const employee of employees.data) {
                next[employee.id] = employee.name;
            }

            return next;
        });
    }

    const extraParams = useMemo(
        () => ({
            premises: premises.length > 0 ? premises : undefined,
            positions: positions.length > 0 ? positions : undefined,
            costCenters: costCenters.length > 0 ? costCenters : undefined,
            contractTypes:
                contractTypes.length > 0 ? contractTypes : undefined,
        }),
        [premises, positions, costCenters, contractTypes],
    );

    const isRowSelected = (id: number): boolean =>
        selection.selectAll
            ? !selection.ids.includes(id)
            : selection.ids.includes(id);

    const toggleRow = (id: number) => {
        if (selection.selectAll) {
            onSelectionChange({
                selectAll: true,
                ids: isRowSelected(id)
                    ? [...selection.ids, id]
                    : selection.ids.filter((excluded) => excluded !== id),
            });

            return;
        }

        onSelectionChange({
            selectAll: false,
            ids: isRowSelected(id)
                ? selection.ids.filter((included) => included !== id)
                : [...selection.ids, id],
        });
    };

    const selectAllMatching = () =>
        onSelectionChange({ selectAll: true, ids: [] });

    const clearSelection = () =>
        onSelectionChange({ selectAll: false, ids: [] });

    const reinclude = (id: number) =>
        onSelectionChange({
            selectAll: true,
            ids: selection.ids.filter((excluded) => excluded !== id),
        });

    const resolvedCount = selection.selectAll
        ? Math.max(employees.total - selection.ids.length, 0)
        : selection.ids.length;

    const hasSelection = resolvedCount > 0;

    const facets: {
        key: FacetKey;
        title: string;
        options: ReportFacetOption[];
        selected: string[];
        onChange: (values: string[]) => void;
    }[] = [
        {
            key: 'premises',
            title: t('ui.payroll_reports.filters.premise'),
            options: filterOptions.premises,
            selected: premises,
            onChange: setPremises,
        },
        {
            key: 'positions',
            title: t('ui.payroll_reports.filters.position'),
            options: filterOptions.positions,
            selected: positions,
            onChange: setPositions,
        },
        {
            key: 'costCenters',
            title: t('ui.payroll_reports.filters.cost_center'),
            options: filterOptions.costCenters,
            selected: costCenters,
            onChange: setCostCenters,
        },
        {
            key: 'contractTypes',
            title: t('ui.payroll_reports.filters.contract_type'),
            options: filterOptions.contractTypes,
            selected: contractTypes,
            onChange: setContractTypes,
        },
    ];

    const chips = facets.flatMap((facet) =>
        facet.selected.map((value) => ({
            key: `${facet.key}:${value}`,
            label: `${facet.title}: ${facet.options.find((option) => option.value === value)?.label ?? value}`,
            remove: () =>
                facet.onChange(facet.selected.filter((v) => v !== value)),
        })),
    );

    const clearAllFilters = () => {
        setPremises([]);
        setPositions([]);
        setCostCenters([]);
        setContractTypes([]);
    };

    const excludedRows =
        selection.selectAll && selection.ids.length > 0
            ? selection.ids.map((id) => ({
                  id,
                  name: nameById[id] ?? `#${id}`,
              }))
            : [];

    const statusTitle = !hasSelection
        ? t('ui.payroll_reports.filters.status.none_title')
        : selection.selectAll
          ? t('ui.payroll_reports.filters.status.all_title')
          : t('ui.payroll_reports.filters.status.manual_title', {
                count: String(resolvedCount),
            });

    const statusSubtitle = !hasSelection
        ? t('ui.payroll_reports.filters.status.none_subtitle')
        : selection.selectAll
          ? selection.ids.length > 0
              ? t('ui.payroll_reports.filters.status.all_subtitle_with_excluded', {
                    count: String(selection.ids.length),
                })
              : t('ui.payroll_reports.filters.status.all_subtitle')
          : t('ui.payroll_reports.filters.status.manual_subtitle');

    const statusAccent = !hasSelection
        ? 'bg-muted-foreground/30'
        : selection.selectAll
          ? 'bg-primary'
          : 'bg-foreground/50';

    const columns = useMemo<ColumnDef<PayrollReportEmployee>[]>(
        () => [
            {
                id: 'select',
                enableSorting: false,
                enableHiding: false,
                meta: {
                    headClassName: 'w-10',
                    cellClassName: 'w-10',
                },
                header: () => null,
                cell: ({ row }) => {
                    const id = row.original.id;
                    const state: SelectionState = isRowSelected(id)
                        ? 'checked'
                        : selection.selectAll
                          ? 'excluded'
                          : 'plain';

                    return (
                        <SelectionIndicator
                            state={state}
                            onClick={() => toggleRow(id)}
                            label={t(
                                'ui.payroll_reports.filters.select_employee',
                            )}
                        />
                    );
                },
            },
            {
                accessorKey: 'name',
                enableSorting: true,
                meta: {
                    title: t('ui.payroll_reports.filters.columns.employee'),
                },
                header: () => t('ui.payroll_reports.filters.columns.employee'),
                cell: ({ row }) => (
                    <div>
                        <div className="font-medium">
                            {row.original.name}
                        </div>
                        <div className="text-sm text-muted-foreground">
                            {row.original.email}
                        </div>
                    </div>
                ),
            },
            {
                id: 'rut',
                enableSorting: false,
                meta: { title: t('ui.payroll_reports.filters.columns.rut') },
                header: () => t('ui.payroll_reports.filters.columns.rut'),
                cell: ({ row }) => row.original.rut ?? '—',
            },
            {
                id: 'position',
                enableSorting: false,
                meta: {
                    title: t('ui.payroll_reports.filters.columns.position'),
                },
                header: () => t('ui.payroll_reports.filters.columns.position'),
                cell: ({ row }) => row.original.position ?? '—',
            },
            {
                id: 'premise',
                enableSorting: false,
                meta: {
                    title: t('ui.payroll_reports.filters.columns.premise'),
                },
                header: () => t('ui.payroll_reports.filters.columns.premise'),
                cell: ({ row }) => row.original.premise ?? '—',
            },
            {
                id: 'cost_center',
                enableSorting: false,
                meta: {
                    title: t(
                        'ui.payroll_reports.filters.columns.cost_center',
                    ),
                },
                header: () =>
                    t('ui.payroll_reports.filters.columns.cost_center'),
                cell: ({ row }) => row.original.cost_center ?? '—',
            },
            {
                id: 'contract_type',
                enableSorting: false,
                meta: {
                    title: t(
                        'ui.payroll_reports.filters.columns.contract_type',
                    ),
                },
                header: () =>
                    t('ui.payroll_reports.filters.columns.contract_type'),
                cell: ({ row }) => row.original.contract_type_label ?? '—',
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [t, selection],
    );

    return (
        <div className="space-y-4">
            <div className="flex flex-col gap-1">
                <span className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    {t('ui.payroll_reports.filters.step_employees')}
                </span>
                <span className="text-base font-semibold">
                    {t('ui.payroll_reports.filters.match_count', {
                        count: String(employees.total),
                    })}
                </span>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                {facets.map((facet) => (
                    <ReportFacetFilter
                        key={facet.key}
                        title={facet.title}
                        options={facet.options}
                        selected={facet.selected}
                        onChange={facet.onChange}
                    />
                ))}

                {chips.length > 0 && (
                    <button
                        type="button"
                        onClick={clearAllFilters}
                        className="px-2 text-sm text-muted-foreground underline underline-offset-2"
                    >
                        {t('ui.payroll_reports.filters.chips.clear_all')}
                    </button>
                )}
            </div>

            {chips.length > 0 && (
                <div className="flex flex-wrap items-center gap-2">
                    {chips.map((chip) => (
                        <button
                            key={chip.key}
                            type="button"
                            onClick={chip.remove}
                            aria-label={t(
                                'ui.payroll_reports.filters.chips.remove',
                            )}
                            className="flex items-center gap-2 rounded-md bg-muted px-2.5 py-1 text-xs text-primary"
                        >
                            <span>{chip.label}</span>
                            <span className="text-muted-foreground">
                                &times;
                            </span>
                        </button>
                    ))}
                </div>
            )}

            <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-muted/50 px-4 py-3">
                <div className="flex items-center gap-3">
                    <span
                        className={cn('h-9 w-1.5 rounded-full', statusAccent)}
                    />
                    <div className="flex flex-col">
                        <span className="text-sm font-medium">
                            {statusTitle}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {statusSubtitle}
                        </span>
                    </div>
                </div>
                <div className="flex gap-2">
                    {!selection.selectAll && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={selectAllMatching}
                        >
                            {t('ui.payroll_reports.filters.select_all')}
                        </Button>
                    )}
                    {hasSelection && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={clearSelection}
                        >
                            {t('ui.payroll_reports.filters.clear_selection')}
                        </Button>
                    )}
                </div>
            </div>

            {excludedRows.length > 0 && (
                <div className="flex flex-wrap items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2.5 dark:border-amber-900 dark:bg-amber-950">
                    <span className="text-xs text-amber-800 dark:text-amber-200">
                        {t('ui.payroll_reports.filters.excluded.title', {
                            count: String(excludedRows.length),
                        })}
                    </span>
                    {excludedRows.map((row) => (
                        <button
                            key={row.id}
                            type="button"
                            onClick={() => reinclude(row.id)}
                            className="flex items-center gap-2 rounded-md bg-white px-2.5 py-1 text-xs text-amber-800 dark:bg-amber-900 dark:text-amber-100"
                        >
                            <span>{row.name}</span>
                            <span className="underline">
                                {t('ui.payroll_reports.filters.excluded.undo')}
                            </span>
                        </button>
                    ))}
                </div>
            )}

            <DataTable
                data={employees}
                columns={columns}
                routeUrl={routeUrl}
                filters={filters}
                extraParams={extraParams}
                only={['employees', 'filters']}
                searchPlaceholder={t(
                    'ui.payroll_reports.filters.search_placeholder',
                )}
                emptyLabel={t('ui.payroll_reports.filters.empty')}
            />
        </div>
    );
}
