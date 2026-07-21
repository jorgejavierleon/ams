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

/** One shift change row (Resolución 38, Art. 27 d.2–d.10). */
type ShiftChangeRow = {
    oldStartDate: string | null;
    oldShift: string | null;
    oldExtension: string | null;
    notificationDate: string | null;
    newStartDate: string;
    newShift: string;
    newExtension: string;
    requestedBy: 'employee' | 'employer';
    observation: string | null;
};

/** A worker's shift-changes block: header data + change rows or a legend. */
type WorkerReport = {
    employee: string;
    employer: string | null;
    premise: string | null;
    rows: ShiftChangeRow[];
    emptyReason: 'fixed-journey' | 'no-changes' | null;
};

type Props = {
    options: ReportOptions;
    filters: ReportFilters;
    report: WorkerReport[];
};

export default function ShiftChangesReport({
    options,
    filters,
    report,
}: Props) {
    const { t } = useTranslations();

    const dash = <span className="text-muted-foreground">–</span>;

    const extension = (value: string | null) =>
        value === null ? dash : t(`ui.shifts.types.${value}`);

    return (
        <>
            <Head title={t('ui.dt.reports.shift-changes.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.dt.reports.shift-changes.title')}
                    description={t('ui.dt.reports.shift-changes.description')}
                />

                <Card>
                    <CardContent>
                        <FilterForm
                            options={options}
                            filters={filters}
                            reportType="shift-changes"
                        />
                    </CardContent>
                </Card>

                {report.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                            <Users className="size-10 text-muted-foreground" />
                            <p className="max-w-md text-sm text-muted-foreground">
                                {t('ui.dt.reports.shift-changes.no_workers')}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-6">
                        <ExportButtons
                            reportType="shift-changes"
                            filters={filters}
                        />

                        {report.map((worker, index) => (
                            <Card key={`${worker.employee}-${index}`}>
                                <CardContent className="space-y-4">
                                    <dl className="grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                        <div className="flex gap-2">
                                            <dt className="font-semibold">
                                                {t(
                                                    'ui.dt.reports.shift-changes.header.employer',
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
                                                    'ui.dt.reports.shift-changes.header.employee',
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
                                                    'ui.dt.reports.shift-changes.header.premise',
                                                )}
                                                :
                                            </dt>
                                            <dd className="text-muted-foreground">
                                                {worker.premise ?? '—'}
                                            </dd>
                                        </div>
                                    </dl>

                                    {worker.rows.length === 0 ? (
                                        <p className="rounded-lg border border-dashed py-6 text-center text-sm text-muted-foreground">
                                            {worker.emptyReason ===
                                            'fixed-journey'
                                                ? t(
                                                      'ui.dt.reports.shift-changes.fixed_journey',
                                                  )
                                                : t(
                                                      'ui.dt.reports.shift-changes.no_changes',
                                                  )}
                                        </p>
                                    ) : (
                                        <div className="overflow-x-auto rounded-lg border">
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>
                                                            {t(
                                                                'ui.dt.reports.shift-changes.columns.old_start_date',
                                                            )}
                                                        </TableHead>
                                                        <TableHead>
                                                            {t(
                                                                'ui.dt.reports.shift-changes.columns.old_shift',
                                                            )}
                                                        </TableHead>
                                                        <TableHead>
                                                            {t(
                                                                'ui.dt.reports.shift-changes.columns.old_extension',
                                                            )}
                                                        </TableHead>
                                                        <TableHead>
                                                            {t(
                                                                'ui.dt.reports.shift-changes.columns.notification_date',
                                                            )}
                                                        </TableHead>
                                                        <TableHead>
                                                            {t(
                                                                'ui.dt.reports.shift-changes.columns.new_start_date',
                                                            )}
                                                        </TableHead>
                                                        <TableHead>
                                                            {t(
                                                                'ui.dt.reports.shift-changes.columns.new_shift',
                                                            )}
                                                        </TableHead>
                                                        <TableHead>
                                                            {t(
                                                                'ui.dt.reports.shift-changes.columns.new_extension',
                                                            )}
                                                        </TableHead>
                                                        <TableHead>
                                                            {t(
                                                                'ui.dt.reports.shift-changes.columns.requested_by',
                                                            )}
                                                        </TableHead>
                                                        <TableHead>
                                                            {t(
                                                                'ui.dt.reports.shift-changes.columns.observations',
                                                            )}
                                                        </TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {worker.rows.map(
                                                        (row, rowIndex) => (
                                                            <TableRow
                                                                key={`${row.newStartDate}-${rowIndex}`}
                                                            >
                                                                <TableCell className="whitespace-nowrap tabular-nums">
                                                                    {row.oldStartDate ??
                                                                        dash}
                                                                </TableCell>
                                                                <TableCell>
                                                                    {row.oldShift ??
                                                                        dash}
                                                                </TableCell>
                                                                <TableCell>
                                                                    {extension(
                                                                        row.oldExtension,
                                                                    )}
                                                                </TableCell>
                                                                <TableCell className="whitespace-nowrap tabular-nums">
                                                                    {row.notificationDate ??
                                                                        dash}
                                                                </TableCell>
                                                                <TableCell className="whitespace-nowrap tabular-nums">
                                                                    {
                                                                        row.newStartDate
                                                                    }
                                                                </TableCell>
                                                                <TableCell>
                                                                    {
                                                                        row.newShift
                                                                    }
                                                                </TableCell>
                                                                <TableCell>
                                                                    {extension(
                                                                        row.newExtension,
                                                                    )}
                                                                </TableCell>
                                                                <TableCell>
                                                                    {t(
                                                                        `ui.dt.reports.shift-changes.requested_by.${row.requestedBy}`,
                                                                    )}
                                                                </TableCell>
                                                                <TableCell className="text-muted-foreground">
                                                                    {row.observation ??
                                                                        dash}
                                                                </TableCell>
                                                            </TableRow>
                                                        ),
                                                    )}
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
