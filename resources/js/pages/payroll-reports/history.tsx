import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useMemo, useState } from 'react';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/hooks/use-translations';
import { history } from '@/routes/payroll-reports';
import type { Paginated } from '@/types/ui';

type PayrollExport = {
    id: number;
    causer: { name: string; email: string } | null;
    report_type: string | null;
    period_start: string | null;
    period_end: string | null;
    format: string | null;
    employee_count: number;
    warned: boolean;
    confirmed: boolean;
    finding_types: string[];
    employees: { id: number; name: string; rut: string | null }[] | null;
    created_at: string;
};

type Props = {
    exports: Paginated<PayrollExport>;
    reportTypes: string[];
    filters: {
        date_from: string | null;
        date_to: string | null;
        report_type: string | null;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
};

const ALL = 'all';

export default function PayrollExportHistoryIndex({
    exports,
    reportTypes,
    filters,
}: Props) {
    const { t } = useTranslations();
    const [detailsTarget, setDetailsTarget] = useState<PayrollExport | null>(
        null,
    );
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [reportType, setReportType] = useState(
        filters.report_type ?? ALL,
    );

    const extraParams = useMemo(
        () => ({
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
            report_type: reportType !== ALL ? reportType : undefined,
        }),
        [dateFrom, dateTo, reportType],
    );

    const columns = useMemo<ColumnDef<PayrollExport>[]>(
        () => [
            {
                accessorKey: 'created_at',
                meta: { title: t('ui.payroll_reports.history.columns.timestamp') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.payroll_reports.history.columns.timestamp')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="font-mono text-xs whitespace-nowrap">
                        {row.original.created_at}
                    </span>
                ),
            },
            {
                accessorKey: 'causer',
                enableSorting: false,
                meta: { title: t('ui.payroll_reports.history.columns.user') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.payroll_reports.history.columns.user')}
                    />
                ),
                cell: ({ row }) =>
                    row.original.causer ? (
                        <div className="flex flex-col">
                            <span className="font-medium">
                                {row.original.causer.name}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {row.original.causer.email}
                            </span>
                        </div>
                    ) : (
                        <span className="text-muted-foreground">—</span>
                    ),
            },
            {
                accessorKey: 'report_type',
                enableSorting: false,
                meta: { title: t('ui.payroll_reports.history.columns.report_type') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.payroll_reports.history.columns.report_type')}
                    />
                ),
                cell: ({ row }) =>
                    row.original.report_type ? (
                        <span className="whitespace-nowrap">
                            {t(`ui.payroll_reports.types.${row.original.report_type}`)}
                        </span>
                    ) : (
                        <span className="text-muted-foreground">—</span>
                    ),
            },
            {
                id: 'period',
                enableSorting: false,
                meta: { title: t('ui.payroll_reports.history.columns.period') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.payroll_reports.history.columns.period')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="whitespace-nowrap">
                        {row.original.period_start} – {row.original.period_end}
                    </span>
                ),
            },
            {
                accessorKey: 'format',
                enableSorting: false,
                meta: { title: t('ui.payroll_reports.history.columns.format') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.payroll_reports.history.columns.format')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="uppercase">{row.original.format}</span>
                ),
            },
            {
                accessorKey: 'employee_count',
                enableSorting: false,
                meta: { title: t('ui.payroll_reports.history.columns.employees') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.payroll_reports.history.columns.employees')}
                    />
                ),
                cell: ({ row }) => row.original.employee_count,
            },
            {
                id: 'status',
                enableSorting: false,
                meta: { title: t('ui.payroll_reports.history.columns.status') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.payroll_reports.history.columns.status')}
                    />
                ),
                cell: ({ row }) =>
                    row.original.warned ? (
                        <Badge variant={row.original.confirmed ? 'outline' : 'destructive'}>
                            {row.original.confirmed
                                ? t('ui.payroll_reports.history.status.warned_confirmed')
                                : t('ui.payroll_reports.history.status.warned')}
                        </Badge>
                    ) : (
                        <Badge variant="secondary">
                            {t('ui.payroll_reports.history.status.clean')}
                        </Badge>
                    ),
            },
            {
                id: 'details',
                enableSorting: false,
                enableHiding: false,
                meta: {
                    title: t('ui.payroll_reports.history.columns.details'),
                    headClassName: 'text-right',
                    cellClassName: 'text-right',
                },
                header: () => null,
                cell: ({ row }) => (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setDetailsTarget(row.original)}
                    >
                        {t('ui.payroll_reports.history.view_details')}
                    </Button>
                ),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('ui.payroll_reports.history.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.payroll_reports.history.title')}
                    description={t('ui.payroll_reports.history.description')}
                />

                <DataTable
                    data={exports}
                    columns={columns}
                    routeUrl={history().url}
                    filters={filters}
                    extraParams={extraParams}
                    only={['exports', 'filters']}
                    emptyLabel={t('ui.payroll_reports.history.empty')}
                    toolbar={
                        <div className="flex flex-wrap items-center gap-3">
                            <div className="flex items-center gap-2">
                                <Label
                                    htmlFor="date_from"
                                    className="text-xs text-muted-foreground"
                                >
                                    {t('ui.payroll_reports.history.filters.date_from')}
                                </Label>
                                <Input
                                    id="date_from"
                                    type="date"
                                    value={dateFrom}
                                    max={dateTo || undefined}
                                    onChange={(event) =>
                                        setDateFrom(event.target.value)
                                    }
                                    className="w-auto"
                                />
                            </div>
                            <div className="flex items-center gap-2">
                                <Label
                                    htmlFor="date_to"
                                    className="text-xs text-muted-foreground"
                                >
                                    {t('ui.payroll_reports.history.filters.date_to')}
                                </Label>
                                <Input
                                    id="date_to"
                                    type="date"
                                    value={dateTo}
                                    min={dateFrom || undefined}
                                    onChange={(event) =>
                                        setDateTo(event.target.value)
                                    }
                                    className="w-auto"
                                />
                            </div>
                            <Select value={reportType} onValueChange={setReportType}>
                                <SelectTrigger className="w-[220px]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        {t(
                                            'ui.payroll_reports.history.filters.all_report_types',
                                        )}
                                    </SelectItem>
                                    {reportTypes.map((type) => (
                                        <SelectItem key={type} value={type}>
                                            {t(`ui.payroll_reports.types.${type}`)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    }
                />
            </div>

            <Dialog
                open={detailsTarget !== null}
                onOpenChange={(open) => !open && setDetailsTarget(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('ui.payroll_reports.history.details_dialog.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t(
                                'ui.payroll_reports.history.details_dialog.description',
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div>
                            <h4 className="mb-1 text-sm font-medium">
                                {t(
                                    'ui.payroll_reports.history.details_dialog.employees_heading',
                                )}
                            </h4>
                            {detailsTarget?.employees?.length ? (
                                <ul className="max-h-48 list-inside list-disc overflow-auto text-sm text-muted-foreground">
                                    {detailsTarget.employees.map((employee) => (
                                        <li key={employee.id}>
                                            {employee.name}
                                            {employee.rut ? ` — ${employee.rut}` : ''}
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'ui.payroll_reports.history.details_dialog.no_employees',
                                    )}
                                </p>
                            )}
                        </div>

                        <div>
                            <h4 className="mb-1 text-sm font-medium">
                                {t(
                                    'ui.payroll_reports.history.details_dialog.findings_heading',
                                )}
                            </h4>
                            {detailsTarget?.finding_types.length ? (
                                <ul className="list-inside list-disc text-sm text-muted-foreground">
                                    {detailsTarget.finding_types.map((type) => (
                                        <li key={type}>
                                            {t(
                                                `ui.payroll_export.finding_types.${type}`,
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'ui.payroll_reports.history.details_dialog.no_findings',
                                    )}
                                </p>
                            )}
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
