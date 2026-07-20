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
    'shift-changes': shiftChanges,
    sundays,
    incidents,
} as const;

const REPORT_TYPES = Object.keys(REPORT_ROUTES) as ReportType[];

type Props = {
    options: ReportOptions;
    filters: ReportFilters;
    /** The report type of the hosting route, if any (null on the landing page). */
    reportType: ReportType | null;
};

/**
 * Shared filter UI for every DT report: report type, date range and optional
 * employee/position/premise multi-selects. Imported by each report page and
 * pre-filled from the URL query params.
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
    });

    const reportTypeOptions = useMemo(
        () =>
            REPORT_TYPES.map((type) => ({
                value: type,
                label: t(`ui.dt.reports.types.${type}`),
            })),
        [t],
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
                    label={t('ui.dt.reports.filters.start')}
                    htmlFor="report-start"
                >
                    <Input
                        id="report-start"
                        type="date"
                        value={data.start}
                        max={data.end || undefined}
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
                        min={data.start || undefined}
                        onChange={(event) => setData('end', event.target.value)}
                    />
                </FormField>

                {/* The technical incidents report (Art. 27 f) is a per-employer
                    log; Art. 24 d) excludes it from the worker-search screen, so
                    only the date range applies to it. */}
                {data.type !== 'incidents' && (
                    <>
                <FormField
                    label={t('ui.dt.reports.filters.employees')}
                    htmlFor="report-employees"
                >
                    <MultiCombobox
                        id="report-employees"
                        options={options.employees}
                        value={data.employees}
                        onChange={(value) => setData('employees', value)}
                        placeholder={t('ui.dt.reports.filters.employees_all')}
                        searchPlaceholder={t(
                            'ui.dt.reports.filters.employees_search',
                        )}
                        emptyLabel={t('ui.dt.reports.filters.no_results')}
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
                        onChange={(value) => setData('positions', value)}
                        placeholder={t('ui.dt.reports.filters.positions_all')}
                        searchPlaceholder={t(
                            'ui.dt.reports.filters.positions_search',
                        )}
                        emptyLabel={t('ui.dt.reports.filters.no_results')}
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
                        placeholder={t('ui.dt.reports.filters.premises_all')}
                        searchPlaceholder={t(
                            'ui.dt.reports.filters.premises_search',
                        )}
                        emptyLabel={t('ui.dt.reports.filters.no_results')}
                        summaryLabel={(count) =>
                            t('ui.dt.reports.filters.selected', {
                                count: String(count),
                            })
                        }
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
