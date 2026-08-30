import { X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types/ui';
import { CollaboratorFilter } from './collaborator-filter';
import { EmployeeAvatar } from './employee-avatar';
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

type FacetKey = 'premises' | 'positions' | 'costCenters' | 'contractTypes';

/**
 * The shared payroll-report filter (RF-7, KOL-19), redesigned as one panel of
 * filter chips (KOL-20): a chip per dimension (sucursal, cargo, centro de
 * costo, tipo de contrato, colaborador), replacing the previous "Paso 1/Paso
 * 2" cards and their DataTable-backed employee table. The period picker lives
 * in the page header instead (owned by the caller), since it applies to the
 * whole page, not just this filter panel. Every RF-1 report (KOL-20..24)
 * imports this and submits its resolved selection to the same aggregation
 * service (KOL-13) and integrity check (KOL-14) this ticket's backend
 * already targets.
 */
export function ReportFilterForm({
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

    // Exclusion banner and the selected-employee pills both need a name for
    // every selected/excluded id; an id can only ever be picked by clicking a
    // row that was visible at the time, so caching every list of employees
    // we've seen (across searches) is enough to always resolve a name later.
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

    const valueChips = facets.flatMap((facet) =>
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
        onSelectionChange({ selectAll: false, ids: [] });
    };

    const selectAllMatching = () =>
        onSelectionChange({ selectAll: true, ids: [] });

    const clearSelection = () =>
        onSelectionChange({ selectAll: false, ids: [] });

    const removeSelected = (id: number) =>
        onSelectionChange({
            selectAll: false,
            ids: selection.ids.filter((included) => included !== id),
        });

    const reinclude = (id: number) =>
        onSelectionChange({
            selectAll: true,
            ids: selection.ids.filter((excluded) => excluded !== id),
        });

    const resolvedCount = selection.selectAll
        ? Math.max(employees.total - selection.ids.length, 0)
        : selection.ids.length;

    const hasSelection = resolvedCount > 0;

    const selectedRows =
        !selection.selectAll && selection.ids.length > 0
            ? selection.ids.map((id) => ({
                  id,
                  name: nameById[id] ?? `#${id}`,
              }))
            : [];

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

    return (
        <div className="space-y-4">
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

                <CollaboratorFilter
                    employees={employees}
                    routeUrl={routeUrl}
                    extraParams={extraParams}
                    selection={selection}
                    onSelectionChange={onSelectionChange}
                />

                {(valueChips.length > 0 || hasSelection) && (
                    <button
                        type="button"
                        onClick={clearAllFilters}
                        className="px-2 text-sm text-muted-foreground underline underline-offset-2"
                    >
                        {t('ui.payroll_reports.filters.chips.clear_all')}
                    </button>
                )}
            </div>

            {valueChips.length > 0 && (
                <div className="flex flex-wrap items-center gap-2">
                    {valueChips.map((chip) => (
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
                <div className="flex flex-wrap items-center gap-3">
                    <span
                        className={cn('h-9 w-1.5 shrink-0 rounded-full', statusAccent)}
                    />
                    <div className="flex flex-col">
                        <span className="text-sm font-medium">
                            {statusTitle}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {statusSubtitle}
                        </span>
                    </div>
                    {selectedRows.length > 0 && (
                        <div className="flex flex-wrap items-center gap-1.5">
                            {selectedRows.map((row) => (
                                <span
                                    key={row.id}
                                    className="inline-flex items-center gap-1.5 rounded-full bg-card py-0.5 pr-1 pl-1.5 text-xs"
                                >
                                    <EmployeeAvatar name={row.name} seed={row.id} />
                                    {row.name}
                                    <button
                                        type="button"
                                        onClick={() => removeSelected(row.id)}
                                        aria-label={t(
                                            'ui.payroll_reports.filters.chips.remove',
                                        )}
                                        className="flex size-4 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
                                    >
                                        <X className="size-3" />
                                    </button>
                                </span>
                            ))}
                        </div>
                    )}
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
        </div>
    );
}
