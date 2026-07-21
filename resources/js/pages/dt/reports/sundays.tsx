import { Head } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { Fragment } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslations } from '@/hooks/use-translations';
import { ExportButtons } from './export-buttons';
import { FilterForm } from './filter-form';
import type { ReportFilters, ReportOptions } from './types';

/** How a day's observation is described (Resolución 38, Art. 27 c.6). */
type Observation =
    | { kind: 'holiday'; name: string }
    | { kind: 'leave'; type: string };

/** One Sunday/holiday in a worker's grid (Resolución 38, Art. 27 c). */
type SundayRow = {
    date: string;
    dayType: 'sunday' | 'holiday';
    holiday: string | null;
    attendance: boolean;
    absence: 'justified' | 'unjustified' | null;
    observation: Observation | null;
};

/** One month block with its rows and a subtotal of days worked (Art. 27 c.7). */
type MonthBlock = {
    key: string;
    label: string;
    worked: number;
    rows: SundayRow[];
};

/** A worker's Sundays/holidays block: header data + month blocks or a legend. */
type WorkerReport = {
    employee: string;
    employer: string | null;
    premise: string | null;
    position: string | null;
    additionalSundays: boolean;
    months: MonthBlock[];
    total: number;
    emptyReason: 'no-sundays' | null;
};

type Props = {
    options: ReportOptions;
    filters: ReportFilters;
    report: WorkerReport[];
};

export default function SundaysReport({ options, filters, report }: Props) {
    const { t } = useTranslations();

    const renderObservation = (observation: Observation | null) => {
        if (observation === null) {
            return null;
        }

        if (observation.kind === 'leave') {
            return t(`ui.leaves.types.${observation.type}`);
        }

        return observation.name;
    };

    return (
        <>
            <Head title={t('ui.dt.reports.sundays.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.dt.reports.sundays.title')}
                    description={t('ui.dt.reports.sundays.description')}
                />

                <Card>
                    <CardContent>
                        <FilterForm
                            options={options}
                            filters={filters}
                            reportType="sundays"
                        />
                    </CardContent>
                </Card>

                {report.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                            <Users className="size-10 text-muted-foreground" />
                            <p className="max-w-md text-sm text-muted-foreground">
                                {t('ui.dt.reports.sundays.no_workers')}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-6">
                        <ExportButtons reportType="sundays" filters={filters} />

                        {report.map((worker, index) => (
                            <Card key={`${worker.employee}-${index}`}>
                                <CardContent className="space-y-4">
                                    <dl className="grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
                                        <div className="flex gap-2">
                                            <dt className="font-semibold">
                                                {t(
                                                    'ui.dt.reports.attendance.header.employer',
                                                )}
                                                :
                                            </dt>
                                            <dd className="text-muted-foreground">
                                                {worker.employer ?? '—'}
                                            </dd>
                                        </div>
                                        <div className="flex gap-2">
                                            <dt className="font-semibold">
                                                {t(
                                                    'ui.dt.reports.attendance.header.employee',
                                                )}
                                                :
                                            </dt>
                                            <dd className="text-muted-foreground">
                                                {worker.employee}
                                            </dd>
                                        </div>
                                        <div className="flex gap-2">
                                            <dt className="font-semibold">
                                                {t(
                                                    'ui.dt.reports.attendance.header.premise',
                                                )}
                                                :
                                            </dt>
                                            <dd className="text-muted-foreground">
                                                {worker.premise ?? '—'}
                                            </dd>
                                        </div>
                                        <div className="flex gap-2">
                                            <dt className="font-semibold">
                                                {t(
                                                    'ui.dt.reports.sundays.header.position',
                                                )}
                                                :
                                            </dt>
                                            <dd className="text-muted-foreground">
                                                {worker.position ?? '—'}
                                            </dd>
                                        </div>
                                    </dl>

                                    {worker.additionalSundays && (
                                        <Badge variant="secondary">
                                            {t(
                                                'ui.dt.reports.sundays.additional_flag',
                                            )}
                                        </Badge>
                                    )}

                                    {worker.months.length === 0 ? (
                                        <p className="rounded-lg border border-dashed py-6 text-center text-sm text-muted-foreground">
                                            {t(
                                                'ui.dt.reports.sundays.no_sundays',
                                            )}
                                        </p>
                                    ) : (
                                        <div className="overflow-x-auto rounded-lg border">
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>
                                                            {t(
                                                                'ui.dt.reports.sundays.columns.additional',
                                                            )}
                                                        </TableHead>
                                                        <TableHead>
                                                            {t(
                                                                'ui.dt.reports.sundays.columns.date',
                                                            )}
                                                        </TableHead>
                                                        <TableHead>
                                                            {t(
                                                                'ui.dt.reports.sundays.columns.attendance',
                                                            )}
                                                        </TableHead>
                                                        <TableHead>
                                                            {t(
                                                                'ui.dt.reports.sundays.columns.absence',
                                                            )}
                                                        </TableHead>
                                                        <TableHead>
                                                            {t(
                                                                'ui.dt.reports.sundays.columns.observations',
                                                            )}
                                                        </TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {worker.months.map(
                                                        (month) => (
                                                            <Fragment
                                                                key={month.key}
                                                            >
                                                                {month.rows.map(
                                                                    (row) => (
                                                                        <TableRow
                                                                            key={
                                                                                row.date
                                                                            }
                                                                            className={
                                                                                row.dayType ===
                                                                                'holiday'
                                                                                    ? 'bg-amber-50 dark:bg-amber-950/20'
                                                                                    : undefined
                                                                            }
                                                                        >
                                                                            <TableCell>
                                                                                {worker.additionalSundays
                                                                                    ? t(
                                                                                          'ui.dt.reports.sundays.yes',
                                                                                      )
                                                                                    : t(
                                                                                          'ui.dt.reports.sundays.no',
                                                                                      )}
                                                                            </TableCell>
                                                                            <TableCell className="whitespace-nowrap tabular-nums">
                                                                                <span className="flex items-center gap-2">
                                                                                    {
                                                                                        row.date
                                                                                    }
                                                                                    {row.dayType ===
                                                                                        'holiday' &&
                                                                                        row.holiday !==
                                                                                            null && (
                                                                                            <Badge variant="outline">
                                                                                                {
                                                                                                    row.holiday
                                                                                                }
                                                                                            </Badge>
                                                                                        )}
                                                                                </span>
                                                                            </TableCell>
                                                                            <TableCell>
                                                                                {row.attendance
                                                                                    ? t(
                                                                                          'ui.dt.reports.sundays.yes',
                                                                                      )
                                                                                    : t(
                                                                                          'ui.dt.reports.sundays.no',
                                                                                      )}
                                                                            </TableCell>
                                                                            <TableCell>
                                                                                {row.absence ===
                                                                                null ? (
                                                                                    <span className="text-muted-foreground">
                                                                                        –
                                                                                    </span>
                                                                                ) : row.absence ===
                                                                                  'justified' ? (
                                                                                    t(
                                                                                        'ui.dt.reports.sundays.justified',
                                                                                    )
                                                                                ) : (
                                                                                    <span className="font-medium text-destructive">
                                                                                        {t(
                                                                                            'ui.dt.reports.sundays.unjustified',
                                                                                        )}
                                                                                    </span>
                                                                                )}
                                                                            </TableCell>
                                                                            <TableCell className="text-muted-foreground">
                                                                                {renderObservation(
                                                                                    row.observation,
                                                                                )}
                                                                            </TableCell>
                                                                        </TableRow>
                                                                    ),
                                                                )}
                                                                <TableRow
                                                                    key={`total-${month.key}`}
                                                                    className="border-t-2 bg-muted/50 font-medium"
                                                                >
                                                                    <TableCell />
                                                                    <TableCell className="whitespace-nowrap capitalize">
                                                                        {t(
                                                                            'ui.dt.reports.sundays.month_total',
                                                                            {
                                                                                month: month.label,
                                                                            },
                                                                        )}
                                                                    </TableCell>
                                                                    <TableCell className="tabular-nums">
                                                                        {
                                                                            month.worked
                                                                        }
                                                                    </TableCell>
                                                                    <TableCell />
                                                                    <TableCell />
                                                                </TableRow>
                                                            </Fragment>
                                                        ),
                                                    )}
                                                    <TableRow className="border-t-2 bg-muted font-semibold">
                                                        <TableCell />
                                                        <TableCell className="whitespace-nowrap">
                                                            {t(
                                                                'ui.dt.reports.sundays.period_total',
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="tabular-nums">
                                                            {worker.total}
                                                        </TableCell>
                                                        <TableCell />
                                                        <TableCell />
                                                    </TableRow>
                                                </TableBody>
                                            </Table>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
