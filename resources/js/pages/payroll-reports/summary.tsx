import { Head, router } from '@inertiajs/react';
import { FileSpreadsheet, FileText, Table2 } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import {
    PayrollExportReadinessWarning
    
} from '@/components/payroll-export-readiness-warning';
import type {PayrollExportFinding} from '@/components/payroll-export-readiness-warning';
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
    PayrollReportEmployee,
    PayrollReportFilterOptions,
    PayrollReportFilters,
    PayrollSummaryReportRow,
    PayrollSummaryReportTotal,
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
    rows: PayrollSummaryReportRow[];
    total: PayrollSummaryReportTotal;
    readiness: Readiness;
};

const EXPORT_FORMATS: { format: 'excel' | 'csv' | 'pdf'; icon: LucideIcon }[] = [
    { format: 'excel', icon: FileSpreadsheet },
    { format: 'csv', icon: Table2 },
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

export default function PayrollSummaryReport({
    period,
    selection: initialSelection,
    selectedEmployeeCount,
    employees,
    filters,
    filterOptions,
    rows,
    total,
    readiness,
}: Props) {
    const { t } = useTranslations();

    const [month, setMonth] = useState(monthValue(period.year, period.month));
    const [selection, setSelection] = useState<EmployeeSelection>(
        initialSelection,
    );
    const [confirmed, setConfirmed] = useState(false);
    const [pendingFormat, setPendingFormat] = useState<string | null>(null);

    const resolvedCount = selection.selectAll
        ? Math.max(employees.total - selection.ids.length, 0)
        : selection.ids.length;

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
        router.get(payrollReportRoutes.summary().url, generationParams(), {
            preserveScroll: true,
        });
    };

    const exportHref = (format: string) =>
        payrollReportRoutes.summary.export(format, {
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
            <Head title={t('ui.payroll_reports.types.payroll-summary')} />

            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={t('ui.payroll_reports.types.payroll-summary')}
                        description={t(
                            'ui.payroll_reports.descriptions.payroll-summary',
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
                            routeUrl={payrollReportRoutes.summary().url}
                            selection={selection}
                            onSelectionChange={setSelection}
                        />
                    </CardContent>
                    <div className="flex items-center justify-between gap-4 border-t bg-muted/40 px-6 py-4">
                        <span className="text-sm">
                            {t('ui.payroll_reports.summary.selection_footer', {
                                count: String(resolvedCount),
                            })}
                        </span>
                        <Button type="button" onClick={handleGenerate}>
                            {t('ui.payroll_reports.summary.generate')}
                        </Button>
                    </div>
                </Card>

                {selectedEmployeeCount === 0 ? (
                    <Card>
                        <CardContent className="py-16 text-center text-sm text-muted-foreground">
                            {t('ui.payroll_reports.summary.no_employees')}
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

                        <Card>
                            <CardContent className="p-0">
                                {rows.length === 0 ? (
                                    <p className="p-6 text-center text-sm text-muted-foreground">
                                        {t(
                                            'ui.payroll_reports.summary.no_rows',
                                        )}
                                    </p>
                                ) : (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>
                                                    {t(
                                                        'ui.payroll_reports.summary.columns.employee',
                                                    )}
                                                </TableHead>
                                                <TableHead>
                                                    {t(
                                                        'ui.payroll_reports.summary.columns.rut',
                                                    )}
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    {t(
                                                        'ui.payroll_reports.summary.columns.worked_hours',
                                                    )}
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    {t(
                                                        'ui.payroll_reports.summary.columns.non_worked_hours',
                                                    )}
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    {t(
                                                        'ui.payroll_reports.summary.columns.lateness',
                                                    )}
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    {t(
                                                        'ui.payroll_reports.summary.columns.overtime_ordinary',
                                                    )}
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    {t(
                                                        'ui.payroll_reports.summary.columns.overtime_sunday_holiday',
                                                    )}
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    {t(
                                                        'ui.payroll_reports.summary.columns.overtime_compensated',
                                                    )}
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    {t(
                                                        'ui.payroll_reports.summary.columns.overtime_unauthorized',
                                                    )}
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    {t(
                                                        'ui.payroll_reports.summary.columns.justified_absences',
                                                    )}
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    {t(
                                                        'ui.payroll_reports.summary.columns.unjustified_absences',
                                                    )}
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    {t(
                                                        'ui.payroll_reports.summary.columns.sundays_holidays_worked',
                                                    )}
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    {t(
                                                        'ui.payroll_reports.summary.columns.paid_worked_days',
                                                    )}
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    {t(
                                                        'ui.payroll_reports.summary.columns.paid_vacation_days',
                                                    )}
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    {t(
                                                        'ui.payroll_reports.summary.columns.paid_leave_days',
                                                    )}
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    {t(
                                                        'ui.payroll_reports.summary.columns.non_paid_unjustified_days',
                                                    )}
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    {t(
                                                        'ui.payroll_reports.summary.columns.non_paid_medical_days',
                                                    )}
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    {t(
                                                        'ui.payroll_reports.summary.columns.non_paid_unpaid_days',
                                                    )}
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {rows.map((row) => (
                                                <TableRow key={row.userId}>
                                                    <TableCell className="font-medium">
                                                        {row.name}
                                                    </TableCell>
                                                    <TableCell>
                                                        {row.rut ?? '—'}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {row.workedHours}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {row.nonWorkedHours}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {row.totalLateness}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {row.overtimeOrdinaryDay}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {
                                                            row.overtimeSundayOrHoliday
                                                        }
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {
                                                            row.overtimeCompensatedInRestDays
                                                        }
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {
                                                            row.overtimeUnauthorized
                                                        }
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {
                                                            row.justifiedAbsenceDays
                                                        }
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {
                                                            row.unjustifiedAbsenceDays
                                                        }
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {
                                                            row.sundaysAndHolidaysWorked
                                                        }
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {row.paidWorkedDays}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {row.paidVacationDays}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {row.paidLeaveDays}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {
                                                            row.nonPaidUnjustifiedAbsenceDays
                                                        }
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {
                                                            row.nonPaidMedicalLeaveDays
                                                        }
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {
                                                            row.nonPaidUnpaidLeaveDays
                                                        }
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                        <TableFooter>
                                            <TableRow>
                                                <TableCell colSpan={2}>
                                                    {t(
                                                        'ui.payroll_reports.summary.total_row',
                                                    )}{' '}
                                                    (
                                                    {t(
                                                        'ui.payroll_reports.summary.employee_count',
                                                        {
                                                            count: String(
                                                                total.employeeCount,
                                                            ),
                                                        },
                                                    )}
                                                    )
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {total.workedHours}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {total.nonWorkedHours}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {total.totalLateness}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {total.overtimeOrdinaryDay}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {
                                                        total.overtimeSundayOrHoliday
                                                    }
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {
                                                        total.overtimeCompensatedInRestDays
                                                    }
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {
                                                        total.overtimeUnauthorized
                                                    }
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {
                                                        total.justifiedAbsenceDays
                                                    }
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {
                                                        total.unjustifiedAbsenceDays
                                                    }
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {
                                                        total.sundaysAndHolidaysWorked
                                                    }
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {total.paidWorkedDays}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {total.paidVacationDays}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {total.paidLeaveDays}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {
                                                        total.nonPaidUnjustifiedAbsenceDays
                                                    }
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {
                                                        total.nonPaidMedicalLeaveDays
                                                    }
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {
                                                        total.nonPaidUnpaidLeaveDays
                                                    }
                                                </TableCell>
                                            </TableRow>
                                        </TableFooter>
                                    </Table>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </>
    );
}
