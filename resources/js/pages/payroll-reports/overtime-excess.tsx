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
    TableFooter,
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
    OvertimeExcessReportWeek,
    PayrollReportEmployee,
    PayrollReportFilterOptions,
    PayrollReportFilters,
    ReportPeriodType,
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
    weeks: OvertimeExcessReportWeek[];
    readiness: Readiness;
};

const EXPORT_FORMATS: { format: 'excel' | 'pdf'; icon: LucideIcon }[] = [
    { format: 'excel', icon: FileSpreadsheet },
    { format: 'pdf', icon: FileText },
];

function monthValue(year: number, month: number): string {
    return `${year}-${String(month).padStart(2, '0')}`;
}

function filenameFrom(response: Response): string {
    const match = /filename="?([^"]+)"?/.exec(
        response.headers.get('content-disposition') ?? '',
    );

    return match?.[1] ?? 'export';
}

export default function OvertimeExcessReport({
    period,
    selection: initialSelection,
    selectedEmployeeCount,
    employees,
    filters,
    filterOptions,
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
        router.get(payrollReportRoutes.overtimeExcess().url, generationParams(), {
            preserveScroll: true,
        });
    };

    const exportHref = (format: string) =>
        payrollReportRoutes.overtimeExcess.export(format, {
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

    const exportsBlocked = readiness.requiresConfirmation && !confirmed;

    return (
        <>
            <Head title={t('ui.payroll_reports.types.overtime-excess')} />

            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={t('ui.payroll_reports.types.overtime-excess')}
                        description={t(
                            'ui.payroll_reports.descriptions.overtime-excess',
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
                            routeUrl={payrollReportRoutes.overtimeExcess().url}
                            selection={selection}
                            onSelectionChange={setSelection}
                        />
                    </CardContent>
                    <div className="flex items-center justify-between gap-4 border-t bg-muted/40 px-6 py-4">
                        <span className="text-sm">
                            {t(
                                'ui.payroll_reports.overtime_excess.selection_footer',
                                { count: String(selectedEmployeeCount) },
                            )}
                        </span>
                        <Button type="button" onClick={handleGenerate}>
                            {t('ui.payroll_reports.overtime_excess.generate')}
                        </Button>
                    </div>
                </Card>

                {selectedEmployeeCount === 0 ? (
                    <Card>
                        <CardContent className="py-16 text-center text-sm text-muted-foreground">
                            {t('ui.payroll_reports.overtime_excess.no_employees')}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        <PayrollExportReadinessWarning
                            findings={readiness.findings}
                            confirmed={confirmed}
                            onConfirmedChange={setConfirmed}
                        />

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
                                                  'ui.payroll_reports.overtime_excess.export.confirm_required',
                                              )
                                            : undefined
                                    }
                                    onClick={() => handleExport(format)}
                                >
                                    <Icon className="size-4" />
                                    {t(
                                        `ui.payroll_reports.overtime_excess.export.${format}`,
                                    )}
                                </Button>
                            ))}
                        </div>

                        {weeks.length === 0 ? (
                            <Card>
                                <CardContent className="py-16 text-center text-sm text-muted-foreground">
                                    {t('ui.payroll_reports.overtime_excess.no_rows')}
                                </CardContent>
                            </Card>
                        ) : (
                            weeks.map((week) => (
                                <Card key={week.start} className="gap-0 overflow-hidden py-0">
                                    <CardHeader className="flex flex-wrap items-center justify-between gap-2 pt-4 pb-3">
                                        <CardTitle className="text-sm font-medium text-muted-foreground">
                                            {t(
                                                'ui.payroll_reports.overtime_excess.week_label',
                                                { start: week.start, end: week.end },
                                            )}
                                        </CardTitle>
                                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                            <span>
                                                {t(
                                                    'ui.payroll_reports.overtime_excess.legal_basis',
                                                    {
                                                        hours: String(week.weeklyOvertimeCapHours),
                                                        reference: week.legalReference,
                                                    },
                                                )}
                                            </span>
                                            {week.employeesOverCapCount > 0 && (
                                                <Badge variant="destructive">
                                                    {t(
                                                        'ui.payroll_reports.overtime_excess.employees_over_cap',
                                                        { count: String(week.employeesOverCapCount) },
                                                    )}
                                                </Badge>
                                            )}
                                        </div>
                                    </CardHeader>
                                    <CardContent className="p-0">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>
                                                        {t('ui.payroll_reports.overtime_excess.columns.employee')}
                                                    </TableHead>
                                                    <TableHead>
                                                        {t('ui.payroll_reports.overtime_excess.columns.rut')}
                                                    </TableHead>
                                                    <TableHead className="text-right">
                                                        {t('ui.payroll_reports.overtime_excess.columns.ordinary_day')}
                                                    </TableHead>
                                                    <TableHead className="text-right">
                                                        {t('ui.payroll_reports.overtime_excess.columns.sunday_holiday')}
                                                    </TableHead>
                                                    <TableHead className="text-right">
                                                        {t('ui.payroll_reports.overtime_excess.columns.compensated_rest_days')}
                                                    </TableHead>
                                                    <TableHead className="text-right">
                                                        {t('ui.payroll_reports.overtime_excess.columns.payable_total')}
                                                    </TableHead>
                                                    <TableHead className="text-right">
                                                        {t('ui.payroll_reports.overtime_excess.columns.unauthorized')}
                                                    </TableHead>
                                                    <TableHead>
                                                        {t('ui.payroll_reports.overtime_excess.columns.cap_exceeded')}
                                                    </TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {week.rows.map((row) => (
                                                    <TableRow key={row.userId}>
                                                        <TableCell className="font-medium">
                                                            {row.name}
                                                        </TableCell>
                                                        <TableCell>
                                                            {row.rut ?? '—'}
                                                        </TableCell>
                                                        <TableCell className="text-right tabular-nums">
                                                            {row.ordinaryDayHours}
                                                        </TableCell>
                                                        <TableCell className="text-right tabular-nums">
                                                            {row.sundayOrHolidayHours}
                                                        </TableCell>
                                                        <TableCell className="text-right tabular-nums">
                                                            {row.compensatedInRestDaysHours}
                                                        </TableCell>
                                                        <TableCell className="text-right font-medium tabular-nums">
                                                            {row.payableTotalHours}
                                                        </TableCell>
                                                        <TableCell className="text-right tabular-nums text-destructive font-semibold">
                                                            {row.unauthorizedHours}
                                                        </TableCell>
                                                        <TableCell>
                                                            {row.capExceeded ? (
                                                                <Badge variant="destructive">
                                                                    {t(
                                                                        'ui.payroll_reports.overtime_excess.cap_exceeded_yes',
                                                                    )}
                                                                </Badge>
                                                            ) : (
                                                                <Badge variant="outline">
                                                                    {t(
                                                                        'ui.payroll_reports.overtime_excess.cap_exceeded_no',
                                                                    )}
                                                                </Badge>
                                                            )}
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                            <TableFooter>
                                                <TableRow>
                                                    <TableCell colSpan={2}>
                                                        {t('ui.payroll_reports.overtime_excess.week_total')}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {week.total.ordinaryDayHours}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {week.total.sundayOrHolidayHours}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {week.total.compensatedInRestDaysHours}
                                                    </TableCell>
                                                    <TableCell className="text-right font-medium tabular-nums">
                                                        {week.total.payableTotalHours}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums text-destructive font-semibold">
                                                        {week.total.unauthorizedHours}
                                                    </TableCell>
                                                    <TableCell />
                                                </TableRow>
                                            </TableFooter>
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
