import type { ColumnDef } from '@tanstack/react-table';
import { useMemo, useState } from 'react';
import { DataTable } from '@/components/data-table';
import { DataTableFacetedFilter } from '@/components/data-table-faceted-filter';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { useTranslations } from '@/hooks/use-translations';
import type { Paginated } from '@/types/ui';
import type {
    EmployeeSelection,
    PayrollReportEmployee,
    PayrollReportFilterOptions,
    PayrollReportFilters,
} from './types';

type Props = {
    employees: Paginated<PayrollReportEmployee>;
    filters: PayrollReportFilters;
    filterOptions: PayrollReportFilterOptions;
    routeUrl: string;
    selection: EmployeeSelection;
    onSelectionChange: (selection: EmployeeSelection) => void;
};

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

    const resolvedCount = selection.selectAll
        ? Math.max(employees.total - selection.ids.length, 0)
        : selection.ids.length;

    const hasSelection = resolvedCount > 0;

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
                cell: ({ row }) => (
                    <Checkbox
                        checked={isRowSelected(row.original.id)}
                        onCheckedChange={() => toggleRow(row.original.id)}
                        aria-label={t(
                            'ui.payroll_reports.filters.select_employee',
                        )}
                    />
                ),
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
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-muted/50 px-4 py-2">
                <span className="text-sm">
                    {t('ui.payroll_reports.filters.selected_count', {
                        count: String(resolvedCount),
                    })}
                </span>
                <div className="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={selectAllMatching}
                    >
                        {t('ui.payroll_reports.filters.select_all')}
                    </Button>
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
                toolbar={
                    <div className="flex flex-wrap items-center gap-2">
                        <DataTableFacetedFilter
                            title={t('ui.payroll_reports.filters.premise')}
                            options={filterOptions.premises}
                            selected={premises}
                            onChange={setPremises}
                        />
                        <DataTableFacetedFilter
                            title={t('ui.payroll_reports.filters.position')}
                            options={filterOptions.positions}
                            selected={positions}
                            onChange={setPositions}
                        />
                        <DataTableFacetedFilter
                            title={t('ui.payroll_reports.filters.cost_center')}
                            options={filterOptions.costCenters}
                            selected={costCenters}
                            onChange={setCostCenters}
                        />
                        <DataTableFacetedFilter
                            title={t(
                                'ui.payroll_reports.filters.contract_type',
                            )}
                            options={filterOptions.contractTypes}
                            selected={contractTypes}
                            onChange={setContractTypes}
                        />
                    </div>
                }
            />
        </div>
    );
}
