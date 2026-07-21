import { Head } from '@inertiajs/react';
import { Users } from 'lucide-react';
import Heading from '@/components/heading';
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

/** How an unjustified/justified absence or an observation is described. */
type Observation =
    | { kind: 'free' }
    | { kind: 'holiday'; name: string }
    | { kind: 'leave'; type: string };

/** One day in a worker's attendance grid (Resolución 38, Art. 27 a). */
type AttendanceRow = {
    date: string;
    attendance: boolean;
    absence: 'justified' | 'unjustified' | null;
    observation: Observation | null;
};

/** A worker's attendance block: header data + one row per day. */
type WorkerReport = {
    employee: string;
    employer: string | null;
    premise: string | null;
    rows: AttendanceRow[];
};

type Props = {
    options: ReportOptions;
    filters: ReportFilters;
    report: WorkerReport[];
};

export default function AttendanceReport({ options, filters, report }: Props) {
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

    return (
        <>
            <Head title={t('ui.dt.reports.attendance.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.dt.reports.attendance.title')}
                    description={t('ui.dt.reports.attendance.description')}
                />

                <Card>
                    <CardContent>
                        <FilterForm
                            options={options}
                            filters={filters}
                            reportType="attendance"
                        />
                    </CardContent>
                </Card>

                {report.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                            <Users className="size-10 text-muted-foreground" />
                            <p className="max-w-md text-sm text-muted-foreground">
                                {t('ui.dt.reports.attendance.no_workers')}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-6">
                        <ExportButtons
                            reportType="attendance"
                            filters={filters}
                        />

                        {report.map((worker, index) => (
                            <Card key={`${worker.employee}-${index}`}>
                                <CardContent className="space-y-4">
                                    <dl className="grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
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
                                    </dl>

                                    <div className="overflow-x-auto rounded-lg border">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>
                                                        {t(
                                                            'ui.dt.reports.attendance.columns.date',
                                                        )}
                                                    </TableHead>
                                                    <TableHead>
                                                        {t(
                                                            'ui.dt.reports.attendance.columns.attendance',
                                                        )}
                                                    </TableHead>
                                                    <TableHead>
                                                        {t(
                                                            'ui.dt.reports.attendance.columns.absence',
                                                        )}
                                                    </TableHead>
                                                    <TableHead>
                                                        {t(
                                                            'ui.dt.reports.attendance.columns.observations',
                                                        )}
                                                    </TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {worker.rows.map((row) => (
                                                    <TableRow key={row.date}>
                                                        <TableCell className="whitespace-nowrap tabular-nums">
                                                            {row.date}
                                                        </TableCell>
                                                        <TableCell>
                                                            {row.attendance
                                                                ? t(
                                                                      'ui.dt.reports.attendance.yes',
                                                                  )
                                                                : t(
                                                                      'ui.dt.reports.attendance.no',
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
                                                                    'ui.dt.reports.attendance.justified',
                                                                )
                                                            ) : (
                                                                <span className="font-medium text-destructive">
                                                                    {t(
                                                                        'ui.dt.reports.attendance.unjustified',
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
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
