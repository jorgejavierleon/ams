import { Head } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
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

/** One outage row of the technical incidents report (Resolución 38, Art. 27 f). */
type IncidentRow = {
    start_time: string;
    end_time: string | null;
    duration: string | null;
    description: string;
};

type Props = {
    options: ReportOptions;
    filters: ReportFilters;
    report: IncidentRow[];
};

export default function IncidentsReport({ options, filters, report }: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.dt.reports.incidents.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.dt.reports.incidents.title')}
                    description={t('ui.dt.reports.incidents.description')}
                />

                <Card>
                    <CardContent>
                        <FilterForm
                            options={options}
                            filters={filters}
                            reportType="incidents"
                        />
                    </CardContent>
                </Card>

                {report.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                            <TriangleAlert className="size-10 text-muted-foreground" />
                            <p className="max-w-md text-sm text-muted-foreground">
                                {t('ui.dt.reports.incidents.empty')}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-6">
                        <ExportButtons
                            reportType="incidents"
                            filters={filters}
                        />

                        <Card>
                            <CardContent>
                                <div className="overflow-x-auto rounded-lg border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>
                                                    {t(
                                                        'ui.dt.reports.incidents.columns.start_time',
                                                    )}
                                                </TableHead>
                                                <TableHead>
                                                    {t(
                                                        'ui.dt.reports.incidents.columns.end_time',
                                                    )}
                                                </TableHead>
                                                <TableHead>
                                                    {t(
                                                        'ui.dt.reports.incidents.columns.duration',
                                                    )}
                                                </TableHead>
                                                <TableHead>
                                                    {t(
                                                        'ui.dt.reports.incidents.columns.description',
                                                    )}
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {report.map((incident, index) => (
                                                <TableRow
                                                    key={`${incident.start_time}-${index}`}
                                                >
                                                    <TableCell className="font-medium whitespace-nowrap tabular-nums">
                                                        {incident.start_time}
                                                    </TableCell>
                                                    <TableCell className="whitespace-nowrap tabular-nums text-muted-foreground">
                                                        {incident.end_time ?? (
                                                            <span className="font-medium text-destructive">
                                                                {t(
                                                                    'ui.dt.reports.incidents.ongoing',
                                                                )}
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="whitespace-nowrap text-muted-foreground">
                                                        {incident.duration ??
                                                            '—'}
                                                    </TableCell>
                                                    <TableCell>
                                                        {incident.description}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </>
    );
}
