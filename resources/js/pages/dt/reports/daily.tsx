import { Head } from '@inertiajs/react';
import { FileSpreadsheet, FileText, FileType, Users } from 'lucide-react';
import { Fragment } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
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
import { FilterForm } from './filter-form';
import type { ReportFilters, ReportOptions } from './types';

/** How a day's observation is described (Resolución 38, Art. 27 b.10). */
type Observation =
    | { kind: 'free' }
    | { kind: 'holiday'; name: string }
    | { kind: 'leave'; type: string };

/** A start/end clock-time pair in hh:mm:ss. */
type TimeRange = { start: string; end: string };

/** One day in the workday grid (Resolución 38, Art. 27 b). */
type DailyRow = {
    date: string;
    journey: TimeRange | null;
    journeyMarks: { in: string | null; out: string | null };
    lunch: TimeRange | null;
    lunchMarks: null;
    undertime: string;
    overtime: string;
    otherMarks: null;
    observation: Observation | null;
};

/** The signed per-column totals closing a week (Art. 27 b.12). */
type WeekTotals = {
    journey: string;
    journeyMarks: string;
    lunch: string;
    undertime: string;
    overtime: string;
    compensation: string;
};

type Week = { days: DailyRow[]; totals: WeekTotals };

/** A worker's workday block: header data + one grid per week. */
type WorkerReport = {
    employee: string;
    employer: string | null;
    premise: string | null;
    hasFlexibleBand: boolean;
    exceptionalCycle: string | null;
    weeks: Week[];
};

type Props = {
    options: ReportOptions;
    filters: ReportFilters;
    report: WorkerReport[];
};

export default function DailyReport({ options, filters, report }: Props) {
    const { t } = useTranslations();

    const renderObservation = (observation: Observation | null) => {
        if (observation === null) {
            return null;
        }

        if (observation.kind === 'free') {
            return t('ui.dt.reports.attendance.observations.free');
        }

        if (observation.kind === 'holiday') {
            return (
                <span title={observation.name} className="cursor-help">
                    {t('ui.dt.reports.attendance.observations.holiday')}
                </span>
            );
        }

        return t(`ui.leaves.types.${observation.type}`);
    };

    const range = (value: TimeRange | null) =>
        value === null ? (
            <span className="text-muted-foreground">–</span>
        ) : (
            `${value.start} – ${value.end}`
        );

    const marks = (value: { in: string | null; out: string | null }) => {
        if (value.in === null && value.out === null) {
            return <span className="text-muted-foreground">–</span>;
        }

        return `${value.in ?? '—'} – ${value.out ?? '—'}`;
    };

    const notApplicable = (
        <span className="text-muted-foreground">
            {t('ui.dt.reports.daily.not_applicable')}
        </span>
    );

    return (
        <>
            <Head title={t('ui.dt.reports.daily.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.dt.reports.daily.title')}
                    description={t('ui.dt.reports.daily.description')}
                />

                <Card>
                    <CardContent>
                        <FilterForm
                            options={options}
                            filters={filters}
                            reportType="daily"
                        />
                    </CardContent>
                </Card>

                {report.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                            <Users className="size-10 text-muted-foreground" />
                            <p className="max-w-md text-sm text-muted-foreground">
                                {t('ui.dt.reports.daily.no_workers')}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-6">
                        {/* Export handlers are wired up in #44; the buttons are
                            present but inert until then. Art. 28 b) requires
                            Excel, PDF and Word. */}
                        <div className="flex items-center justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled
                            >
                                <FileSpreadsheet className="size-4" />
                                {t('ui.dt.reports.daily.export.excel')}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled
                            >
                                <FileText className="size-4" />
                                {t('ui.dt.reports.daily.export.pdf')}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled
                            >
                                <FileType className="size-4" />
                                {t('ui.dt.reports.daily.export.word')}
                            </Button>
                        </div>

                        {report.map((worker, index) => {
                            const showCycle = worker.exceptionalCycle !== null;

                            return (
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
                                                        'ui.dt.reports.daily.header.flexible_band',
                                                    )}
                                                    :
                                                </dt>
                                                <dd className="text-muted-foreground">
                                                    {worker.hasFlexibleBand
                                                        ? t(
                                                              'ui.dt.reports.daily.yes',
                                                          )
                                                        : t(
                                                              'ui.dt.reports.daily.no',
                                                          )}
                                                </dd>
                                            </div>
                                        </dl>

                                        <div className="overflow-x-auto rounded-lg border">
                                            <Table className="text-xs">
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>
                                                            {t(
                                                                'ui.dt.reports.daily.columns.date',
                                                            )}
                                                        </TableHead>
                                                        <TableHead>
                                                            {t(
                                                                'ui.dt.reports.daily.columns.journey',
                                                            )}
                                                        </TableHead>
                                                        <TableHead>
                                                            {t(
                                                                'ui.dt.reports.daily.columns.journey_marks',
                                                            )}
                                                        </TableHead>
                                                        <TableHead>
                                                            {t(
                                                                'ui.dt.reports.daily.columns.lunch',
                                                            )}
                                                        </TableHead>
                                                        <TableHead>
                                                            {t(
                                                                'ui.dt.reports.daily.columns.lunch_marks',
                                                            )}
                                                        </TableHead>
                                                        <TableHead>
                                                            {t(
                                                                'ui.dt.reports.daily.columns.undertime',
                                                            )}
                                                        </TableHead>
                                                        <TableHead>
                                                            {t(
                                                                'ui.dt.reports.daily.columns.overtime',
                                                            )}
                                                        </TableHead>
                                                        <TableHead>
                                                            {t(
                                                                'ui.dt.reports.daily.columns.other_marks',
                                                            )}
                                                        </TableHead>
                                                        <TableHead>
                                                            {t(
                                                                'ui.dt.reports.daily.columns.observations',
                                                            )}
                                                        </TableHead>
                                                        {showCycle && (
                                                            <TableHead>
                                                                {t(
                                                                    'ui.dt.reports.daily.columns.exceptional_cycle',
                                                                )}
                                                            </TableHead>
                                                        )}
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {worker.weeks.map(
                                                        (week, weekIndex) => (
                                                            <Fragment
                                                                key={weekIndex}
                                                            >
                                                                {week.days.map(
                                                                    (row) => (
                                                                        <TableRow
                                                                            key={
                                                                                row.date
                                                                            }
                                                                        >
                                                                            <TableCell className="whitespace-nowrap tabular-nums">
                                                                                {
                                                                                    row.date
                                                                                }
                                                                            </TableCell>
                                                                            <TableCell className="whitespace-nowrap tabular-nums">
                                                                                {range(
                                                                                    row.journey,
                                                                                )}
                                                                            </TableCell>
                                                                            <TableCell className="whitespace-nowrap tabular-nums">
                                                                                {marks(
                                                                                    row.journeyMarks,
                                                                                )}
                                                                            </TableCell>
                                                                            <TableCell className="whitespace-nowrap tabular-nums">
                                                                                {row.lunch ===
                                                                                null
                                                                                    ? notApplicable
                                                                                    : range(
                                                                                          row.lunch,
                                                                                      )}
                                                                            </TableCell>
                                                                            <TableCell>
                                                                                {
                                                                                    notApplicable
                                                                                }
                                                                            </TableCell>
                                                                            <TableCell className="whitespace-nowrap text-destructive tabular-nums">
                                                                                {
                                                                                    row.undertime
                                                                                }
                                                                            </TableCell>
                                                                            <TableCell className="whitespace-nowrap text-emerald-600 tabular-nums dark:text-emerald-400">
                                                                                {
                                                                                    row.overtime
                                                                                }
                                                                            </TableCell>
                                                                            <TableCell>
                                                                                {
                                                                                    notApplicable
                                                                                }
                                                                            </TableCell>
                                                                            <TableCell className="text-muted-foreground">
                                                                                {renderObservation(
                                                                                    row.observation,
                                                                                )}
                                                                            </TableCell>
                                                                            {showCycle && (
                                                                                <TableCell className="text-muted-foreground">
                                                                                    {
                                                                                        worker.exceptionalCycle
                                                                                    }
                                                                                </TableCell>
                                                                            )}
                                                                        </TableRow>
                                                                    ),
                                                                )}
                                                                <TableRow
                                                                    key={`total-${weekIndex}`}
                                                                    className="border-t-2 bg-muted/50 font-medium"
                                                                >
                                                                    <TableCell className="whitespace-nowrap">
                                                                        {t(
                                                                            'ui.dt.reports.daily.week_total',
                                                                        )}
                                                                    </TableCell>
                                                                    <TableCell className="whitespace-nowrap tabular-nums">
                                                                        {
                                                                            week
                                                                                .totals
                                                                                .journey
                                                                        }
                                                                    </TableCell>
                                                                    <TableCell className="whitespace-nowrap tabular-nums">
                                                                        {
                                                                            week
                                                                                .totals
                                                                                .journeyMarks
                                                                        }
                                                                    </TableCell>
                                                                    <TableCell className="whitespace-nowrap tabular-nums">
                                                                        {
                                                                            week
                                                                                .totals
                                                                                .lunch
                                                                        }
                                                                    </TableCell>
                                                                    <TableCell />
                                                                    <TableCell className="whitespace-nowrap text-destructive tabular-nums">
                                                                        {
                                                                            week
                                                                                .totals
                                                                                .undertime
                                                                        }
                                                                    </TableCell>
                                                                    <TableCell className="whitespace-nowrap text-emerald-600 tabular-nums dark:text-emerald-400">
                                                                        {
                                                                            week
                                                                                .totals
                                                                                .overtime
                                                                        }
                                                                    </TableCell>
                                                                    <TableCell />
                                                                    <TableCell className="whitespace-nowrap tabular-nums">
                                                                        <span className="text-muted-foreground">
                                                                            {t(
                                                                                'ui.dt.reports.daily.compensation',
                                                                            )}

                                                                            :{' '}
                                                                        </span>
                                                                        {
                                                                            week
                                                                                .totals
                                                                                .compensation
                                                                        }
                                                                    </TableCell>
                                                                    {showCycle && (
                                                                        <TableCell />
                                                                    )}
                                                                </TableRow>
                                                            </Fragment>
                                                        ),
                                                    )}
                                                </TableBody>
                                            </Table>
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>
        </>
    );
}
