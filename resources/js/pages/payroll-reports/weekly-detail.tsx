import { Head, router } from '@inertiajs/react';
import { FileSpreadsheet, FileText } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { PayrollExportReadinessWarning } from '@/components/payroll-export-readiness-warning';
import type { PayrollExportFinding } from '@/components/payroll-export-readiness-warning';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslations } from '@/hooks/use-translations';
import payrollReportRoutes from '@/routes/payroll-reports';
import type { Paginated } from '@/types/ui';
import { PeriodSelector } from './period-selector';
import { ReportFilterForm } from './report-filter-form';
import type {
    EmployeeSelection,
    PayrollReportEmployee,
    PayrollReportFilterOptions,
    PayrollReportFilters,
    ReportPeriodType,
    WeeklyDetailReportEmployee,
    WeeklyDetailReportWeek,
} from './types';

type Readiness = {
    findings: PayrollExportFinding[];
    isClean: boolean;
    requiresConfirmation: boolean;
};

type Props = {
    period: { year: number; month: number; type: ReportPeriodType };
    selection: EmployeeSelection;
    selectedEmployeeCount: number;
    employees: Paginated<PayrollReportEmployee>;
    filters: PayrollReportFilters;
    filterOptions: PayrollReportFilterOptions;
    employee: WeeklyDetailReportEmployee | null;
    weeks: WeeklyDetailReportWeek[];
    readiness: Readiness | null;
};

const EXPORT_FORMATS: { format: 'excel' | 'pdf'; icon: LucideIcon }[] = [
    { format: 'excel', icon: FileSpreadsheet },
    { format: 'pdf', icon: FileText },
];

const STATUS_BADGE: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    success: 'default',
    warning: 'secondary',
    destructive: 'destructive',
};

function monthValue(year: number, month: number): string {
    return `${year}-${String(month).padStart(2, '0')}`;
}

function filenameFrom(response: Response): string {
    const match = /filename="?([^"]+)"?/.exec(
        response.headers.get('content-disposition') ?? '',
    );

    return match?.[1] ?? 'export';
}

export default function WeeklyDetailReport({
    period,
    selection: initialSelection,
    selectedEmployeeCount,
    employees,
    filters,
    filterOptions,
    employee,
    weeks,
    readiness,
}: Props) {
    const { t } = useTranslations();

    const [month, setMonth] = useState(monthValue(period.year, period.month));
    const [selection, setSelection] = useState<EmployeeSelection>(
        initialSelection,
    );
    const [confirmed, setConfirmed] = useState(false);
    const [pendingFormat, setPendingFormat] = useState<string | null>(null);

    const generationParams = () => {
        const [year, monthNumber] = month.split('-').map(Number);

        return {
            period_year: year,
            period_month: monthNumber,
            period_type: 'month',
            selectAll: selection.selectAll ? 1 : 0,
            ids: selection.ids,
            premises: filters.premises,
            costCenters: filters.costCenters,
            positions: filters.positions,
            contractTypes: filters.contractTypes,
        };
    };

    const handleGenerate = () => {
        router.get(payrollReportRoutes.weeklyDetail().url, generationParams(), {
            preserveScroll: true,
        });
    };

    const exportHref = (format: string) =>
        payrollReportRoutes.weeklyDetail.export(format, {
            query: { ...generationParams(), confirmed: confirmed ? 1 : 0 },
        }).url;

    const handleExport = async (format: string) => {
        setPendingFormat(format);

        try {
            const response = await fetch(exportHref(format));

            if (
                response.headers
                    .get('content-type')
                    ?.includes('application/json')
            ) {
                const payload = (await response.json()) as { message: string };
                toast.error(payload.message);

                return;
            }

            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filenameFrom(response);
            link.click();
            URL.revokeObjectURL(url);
        } finally {
            setPendingFormat(null);
        }
    };

    const exportsBlocked =
        readiness !== null && readiness.requiresConfirmation && !confirmed;

    return (
        <>
            <Head title={t('ui.payroll_reports.types.weekly-detail')} />

            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={t('ui.payroll_reports.types.weekly-detail')}
                        description={t(
                            'ui.payroll_reports.descriptions.weekly-detail',
                        )}
                    />
                    <PeriodSelector month={month} onMonthChange={setMonth} />
                </div>

                <Card className="gap-0 overflow-hidden py-0">
                    <CardHeader className="pt-6 pb-4">
                        <CardTitle className="text-base">
                            {t('ui.payroll_reports.filters.title')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4 pb-6">
                        <ReportFilterForm
                            employees={employees}
                            filters={filters}
                            filterOptions={filterOptions}
                            routeUrl={payrollReportRoutes.weeklyDetail().url}
                            selection={selection}
                            onSelectionChange={setSelection}
                        />
                    </CardContent>
                    <div className="flex items-center justify-between gap-4 border-t bg-muted/40 px-6 py-4">
                        <span className="text-sm">
                            {t(
                                'ui.payroll_reports.weekly_detail.selection_footer',
                                { count: String(selectedEmployeeCount) },
                            )}
                        </span>
                        <Button type="button" onClick={handleGenerate}>
                            {t('ui.payroll_reports.summary.generate')}
                        </Button>
                    </div>
                </Card>

                {selectedEmployeeCount !== 1 || employee === null ? (
                    <Card>
                        <CardContent className="py-16 text-center text-sm text-muted-foreground">
                            {t(
                                'ui.payroll_reports.weekly_detail.select_one_required',
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        <div className="text-sm font-medium">
                            {employee.name}
                            {employee.rut ? ` — ${employee.rut}` : ''}
                        </div>

                        {readiness && (
                            <PayrollExportReadinessWarning
                                findings={readiness.findings}
                                confirmed={confirmed}
                                onConfirmedChange={setConfirmed}
                            />
                        )}

                        <div className="flex items-center justify-end gap-2">
                            {EXPORT_FORMATS.map(({ format, icon: Icon }) => (
                                <Button
                                    key={format}
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={
                                        pendingFormat === format ||
                                        exportsBlocked
                                    }
                                    title={
                                        exportsBlocked
                                            ? t(
                                                  'ui.payroll_reports.summary.export.confirm_required',
                                              )
                                            : undefined
                                    }
                                    onClick={() => handleExport(format)}
                                >
                                    <Icon className="size-4" />
                                    {t(
                                        `ui.payroll_reports.summary.export.${format}`,
                                    )}
                                </Button>
                            ))}
                        </div>

                        {weeks.length === 0 ? (
                            <Card>
                                <CardContent className="py-16 text-center text-sm text-muted-foreground">
                                    {t(
                                        'ui.payroll_reports.summary.no_rows',
                                    )}
                                </CardContent>
                            </Card>
                        ) : (
                            weeks.map((week) => (
                                <Card key={week.start} className="gap-0 overflow-hidden py-0">
                                    <CardHeader className="pt-4 pb-3">
                                        <CardTitle className="text-sm font-medium text-muted-foreground">
                                            {t(
                                                'ui.payroll_reports.weekly_detail.week_label',
                                                { start: week.start, end: week.end },
                                            )}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="p-0">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>
                                                        {t('ui.payroll_reports.weekly_detail.columns.day')}
                                                    </TableHead>
                                                    <TableHead>
                                                        {t('ui.payroll_reports.weekly_detail.columns.status')}
                                                    </TableHead>
                                                    <TableHead className="text-right">
                                                        {t('ui.payroll_reports.weekly_detail.groups.entry')} {t('ui.payroll_reports.weekly_detail.columns.real')}
                                                    </TableHead>
                                                    <TableHead className="text-right">
                                                        {t('ui.payroll_reports.weekly_detail.groups.entry')} {t('ui.payroll_reports.weekly_detail.columns.theoretical')}
                                                    </TableHead>
                                                    <TableHead className="text-right">
                                                        {t('ui.payroll_reports.weekly_detail.columns.difference')}
                                                    </TableHead>
                                                    <TableHead className="text-right">
                                                        {t('ui.payroll_reports.weekly_detail.groups.exit')} {t('ui.payroll_reports.weekly_detail.columns.real')}
                                                    </TableHead>
                                                    <TableHead className="text-right">
                                                        {t('ui.payroll_reports.weekly_detail.groups.exit')} {t('ui.payroll_reports.weekly_detail.columns.theoretical')}
                                                    </TableHead>
                                                    <TableHead className="text-right">
                                                        {t('ui.payroll_reports.weekly_detail.columns.difference')}
                                                    </TableHead>
                                                    <TableHead className="text-right">
                                                        {t('ui.payroll_reports.weekly_detail.groups.lunch')}
                                                    </TableHead>
                                                    <TableHead>
                                                        {t('ui.payroll_reports.weekly_detail.columns.observation')}
                                                    </TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {week.days.map((day) => (
                                                    <TableRow key={day.date}>
                                                        <TableCell className="font-medium capitalize">
                                                            {day.weekday_label} {day.date_label}
                                                        </TableCell>
                                                        <TableCell>
                                                            {day.status_label ? (
                                                                <Badge
                                                                    variant={
                                                                        STATUS_BADGE[
                                                                            day.status_badge ?? ''
                                                                        ] ?? 'outline'
                                                                    }
                                                                >
                                                                    {day.status_label}
                                                                </Badge>
                                                            ) : (
                                                                '—'
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="text-right tabular-nums">
                                                            {day.entry.real ?? '—'}
                                                        </TableCell>
                                                        <TableCell className="text-right tabular-nums">
                                                            {day.entry.theoretical ?? '—'}
                                                        </TableCell>
                                                        <TableCell className="text-right tabular-nums">
                                                            {day.entry.difference ?? '—'}
                                                        </TableCell>
                                                        <TableCell className="text-right tabular-nums">
                                                            {day.exit.real ?? '—'}
                                                        </TableCell>
                                                        <TableCell className="text-right tabular-nums">
                                                            {day.exit.theoretical ?? '—'}
                                                        </TableCell>
                                                        <TableCell className="text-right tabular-nums">
                                                            {day.exit.difference ?? '—'}
                                                        </TableCell>
                                                        <TableCell className="text-right tabular-nums">
                                                            {day.lunch.theoretical_start && day.lunch.theoretical_end
                                                                ? `${day.lunch.theoretical_start} - ${day.lunch.theoretical_end}`
                                                                : t('ui.payroll_reports.weekly_detail.lunch_not_applicable')}
                                                        </TableCell>
                                                        <TableCell className="space-x-1 text-xs">
                                                            {day.leave && (
                                                                <span>{day.leave.type_label}</span>
                                                            )}
                                                            {day.has_pending_modification && (
                                                                <Badge variant="secondary">
                                                                    {t('ui.payroll_reports.weekly_detail.pending_modification')}
                                                                </Badge>
                                                            )}
                                                            {day.has_approved_modification && (
                                                                <Badge variant="outline">
                                                                    {t('ui.payroll_reports.weekly_detail.approved_modification')}
                                                                </Badge>
                                                            )}
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </CardContent>
                                </Card>
                            ))
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
