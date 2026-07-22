import { router, useForm } from '@inertiajs/react';
import { FileBarChart } from 'lucide-react';
import { useMemo } from 'react';
import { FormField } from '@/components/form-field';
import { MultiCombobox } from '@/components/multi-combobox';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/hooks/use-translations';
import {
    attendance,
    daily,
    incidents,
    shiftChanges,
    sundays,
} from '@/routes/dt/reports';
import type { ReportFilters, ReportOptions, ReportType } from './types';

/**
 * Maps each report type to its Wayfinder route. Submitting the filter navigates
 * to the selected report with the current filter state as query params, so the
 * resulting report page is bookmarkable and shareable.
 */
const REPORT_ROUTES = {
    attendance,
    daily,
    sundays,
    'shift-changes': shiftChanges,
    incidents,
} as const;

const REPORT_TYPES = Object.keys(REPORT_ROUTES) as ReportType[];

/** `Y-m-d` for a date, matching the format the backend filters parse. */
const toIso = (date: Date): string => date.toISOString().slice(0, 10);

/**
 * The preset date ranges Resolución 38, Art. 25.1.c) requires — última semana,
 * quincena, mes anterior — plus últimos 12 meses for the sundays/holidays
 * report. Each resolves a `{ start, end }` pair against today.
 */
const PERIOD_PRESETS = {
    week: () => {
        const end = new Date();
        const start = new Date();
        start.setDate(start.getDate() - 7);

        return { start: toIso(start), end: toIso(end) };
    },
    fortnight: () => {
        const end = new Date();
        const start = new Date();
        start.setDate(start.getDate() - 15);

        return { start: toIso(start), end: toIso(end) };
    },
    last_month: () => {
        const now = new Date();
        const start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        const end = new Date(now.getFullYear(), now.getMonth(), 0);

        return { start: toIso(start), end: toIso(end) };
    },
    last_12_months: () => {
        const end = new Date();
        const start = new Date();
        start.setMonth(start.getMonth() - 12);

        return { start: toIso(start), end: toIso(end) };
    },
} as const;

type Props = {
    options: ReportOptions;
    filters: ReportFilters;
    /** The report type of the hosting route, if any (null on the landing page). */
    reportType: ReportType | null;
};

/**
 * Shared filter UI for every DT report (Resolución 38, Art. 25). All parameters
 * stay visible and usable in any order (Art. 25.2): report type, preset/explicit
 * date range bounded to the last five years, and — for worker reports —
 * employees, positions, premises, jornada (shift types), turnos (shifts) and a
 * hash/checksum lookup. Imported by each report page and pre-filled from the URL.
 */
export function FilterForm({ options, filters, reportType }: Props) {
    const { t } = useTranslations();

    const { data, setData, processing } = useForm({
        type: (reportType ?? filters.type ?? '') as ReportType | '',
        start: filters.start,
        end: filters.end,
        employees: filters.employees.map(String),
        positions: filters.positions.map(String),
        premises: filters.premises.map(String),
        journals: filters.journals,
        shifts: filters.shifts.map(String),
        checksum: filters.checksum ?? '',
    });

    const reportTypeOptions = useMemo(
        () =>
            REPORT_TYPES.map((type) => ({
                value: type,
                label: t(`ui.dt.reports.types.${type}`),
            })),
        [t],
    );

    // Art. 25.1.d): the range spans at most the last five years, up to today.
    const { minDate, maxDate } = useMemo(() => {
        const today = new Date();
        const min = new Date();
        min.setFullYear(min.getFullYear() - 5);

        return { minDate: toIso(min), maxDate: toIso(today) };
    }, []);

    const applyPreset = (preset: keyof typeof PERIOD_PRESETS) => {
        const { start, end } = PERIOD_PRESETS[preset]();
        setData((current) => ({ ...current, start, end }));
    };

    const isIncidents = data.type === 'incidents';

    // Art. 25.1.c): última semana, quincena and mes anterior are offered for
    // every report; the 12-month preset only for the sundays/holidays report.
    const periodPresets = (
        Object.keys(PERIOD_PRESETS) as (keyof typeof PERIOD_PRESETS)[]
    ).filter(
        (preset) => preset !== 'last_12_months' || data.type === 'sundays',
    );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (data.type === '') {
            return;
        }

        router.get(REPORT_ROUTES[data.type]().url, {
            start: data.start,
            end: data.end,
            employees: data.employees,
            positions: data.positions,
            premises: data.premises,
            journals: data.journals,
            shifts: data.shifts,
            checksum: data.checksum || undefined,
        });
    };

    return (
        <form onSubmit={submit} noValidate className="space-y-6">
            <div className="grid gap-4 md:grid-cols-2">
                <FormField
                    label={t('ui.dt.reports.filters.type')}
                    htmlFor="report-type"
                    className="md:col-span-2"
                >
                    <Select
                        value={data.type}
                        onValueChange={(value) =>
                            setData('type', value as ReportType)
                        }
                    >
                        <SelectTrigger id="report-type">
                            <SelectValue
                                placeholder={t(
                                    'ui.dt.reports.filters.type_placeholder',
                                )}
                            />
                        </SelectTrigger>
                        <SelectContent>
                            {reportTypeOptions.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>

                <FormField
                    label={t('ui.dt.reports.filters.periods.label')}
                    htmlFor="report-periods"
                    className="md:col-span-2"
                >
                    <div id="report-periods" className="flex flex-wrap gap-2">
                        {periodPresets.map((preset) => (
                            <Button
                                key={preset}
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => applyPreset(preset)}
                            >
                                {t(`ui.dt.reports.filters.periods.${preset}`)}
                            </Button>
                        ))}
                    </div>
                </FormField>

                <FormField
                    label={t('ui.dt.reports.filters.start')}
                    htmlFor="report-start"
                >
                    <Input
                        id="report-start"
                        type="date"
                        value={data.start}
                        min={minDate}
                        max={data.end || maxDate}
                        onChange={(event) =>
                            setData('start', event.target.value)
                        }
                    />
                </FormField>

                <FormField
                    label={t('ui.dt.reports.filters.end')}
                    htmlFor="report-end"
                >
                    <Input
                        id="report-end"
                        type="date"
                        value={data.end}
                        min={data.start || minDate}
                        max={maxDate}
                        onChange={(event) => setData('end', event.target.value)}
                    />
                </FormField>

                {/* The technical incidents report (Art. 27 f) is a per-employer
                    log; Art. 24 d) excludes it from the worker-search screen, so
                    only the date range applies to it. */}
                {!isIncidents && (
                    <>
                        <FormField
                            label={t('ui.dt.reports.filters.employees')}
                            htmlFor="report-employees"
                        >
                            <MultiCombobox
                                id="report-employees"
                                options={options.employees}
                                value={data.employees}
                                onChange={(value) =>
                                    setData('employees', value)
                                }
                                placeholder={t(
                                    'ui.dt.reports.filters.employees_all',
                                )}
                                searchPlaceholder={t(
                                    'ui.dt.reports.filters.employees_search',
                                )}
                                emptyLabel={t(
                                    'ui.dt.reports.filters.no_results',
                                )}
                                summaryLabel={(count) =>
                                    t('ui.dt.reports.filters.selected', {
                                        count: String(count),
                                    })
                                }
                            />
                        </FormField>

                        <FormField
                            label={t('ui.dt.reports.filters.positions')}
                            htmlFor="report-positions"
                        >
                            <MultiCombobox
                                id="report-positions"
                                options={options.positions}
                                value={data.positions}
                                onChange={(value) =>
                                    setData('positions', value)
                                }
                                placeholder={t(
                                    'ui.dt.reports.filters.positions_all',
                                )}
                                searchPlaceholder={t(
                                    'ui.dt.reports.filters.positions_search',
                                )}
                                emptyLabel={t(
                                    'ui.dt.reports.filters.no_results',
                                )}
                                summaryLabel={(count) =>
                                    t('ui.dt.reports.filters.selected', {
                                        count: String(count),
                                    })
                                }
                            />
                        </FormField>

                        <FormField
                            label={t('ui.dt.reports.filters.premises')}
                            htmlFor="report-premises"
                        >
                            <MultiCombobox
                                id="report-premises"
                                options={options.premises}
                                value={data.premises}
                                onChange={(value) => setData('premises', value)}
                                placeholder={t(
                                    'ui.dt.reports.filters.premises_all',
                                )}
                                searchPlaceholder={t(
                                    'ui.dt.reports.filters.premises_search',
                                )}
                                emptyLabel={t(
                                    'ui.dt.reports.filters.no_results',
                                )}
                                summaryLabel={(count) =>
                                    t('ui.dt.reports.filters.selected', {
                                        count: String(count),
                                    })
                                }
                            />
                        </FormField>

                        <FormField
                            label={t('ui.dt.reports.filters.journals')}
                            htmlFor="report-journals"
                        >
                            <MultiCombobox
                                id="report-journals"
                                options={options.journals}
                                value={data.journals}
                                onChange={(value) => setData('journals', value)}
                                placeholder={t(
                                    'ui.dt.reports.filters.journals_all',
                                )}
                                searchPlaceholder={t(
                                    'ui.dt.reports.filters.journals_search',
                                )}
                                emptyLabel={t(
                                    'ui.dt.reports.filters.no_results',
                                )}
                                summaryLabel={(count) =>
                                    t('ui.dt.reports.filters.selected', {
                                        count: String(count),
                                    })
                                }
                            />
                        </FormField>

                        <FormField
                            label={t('ui.dt.reports.filters.shifts')}
                            htmlFor="report-shifts"
                        >
                            <MultiCombobox
                                id="report-shifts"
                                options={options.shifts}
                                value={data.shifts}
                                onChange={(value) => setData('shifts', value)}
                                placeholder={t(
                                    'ui.dt.reports.filters.shifts_all',
                                )}
                                searchPlaceholder={t(
                                    'ui.dt.reports.filters.shifts_search',
                                )}
                                emptyLabel={t(
                                    'ui.dt.reports.filters.no_results',
                                )}
                                summaryLabel={(count) =>
                                    t('ui.dt.reports.filters.selected', {
                                        count: String(count),
                                    })
                                }
                            />
                        </FormField>

                        {/* Hash/checksum lookup (Art. 25.1.j): narrows the report
                            to the worker owning the matching mark. Distinct from
                            the standalone "Validar marca" page. */}
                        <FormField
                            label={t('ui.dt.reports.filters.checksum')}
                            htmlFor="report-checksum"
                        >
                            <Input
                                id="report-checksum"
                                type="text"
                                value={data.checksum}
                                onChange={(event) =>
                                    setData('checksum', event.target.value)
                                }
                                placeholder={t(
                                    'ui.dt.reports.filters.checksum_placeholder',
                                )}
                            />
                        </FormField>
                    </>
                )}
            </div>

            <div className="flex justify-end">
                <Button type="submit" disabled={data.type === '' || processing}>
                    <FileBarChart className="size-4" />
                    {t('ui.dt.reports.filters.generate')}
                </Button>
            </div>
        </form>
    );
}
