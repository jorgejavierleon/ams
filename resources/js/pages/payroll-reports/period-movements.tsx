import { Head, router } from '@inertiajs/react';
import { FileSpreadsheet } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTranslations } from '@/hooks/use-translations';
import payrollReportRoutes from '@/routes/payroll-reports';
import type { Paginated } from '@/types/ui';
import { PeriodSelector } from './period-selector';
import { ReportFilterForm } from './report-filter-form';
import type {
    EmployeeSelection,
    MovementsEmployeeRow,
    MovementsLeaveRow,
    MovementsShiftChangeBlock,
    PayrollReportEmployee,
    PayrollReportFilterOptions,
    PayrollReportFilters,
    PeriodMovementsReport,
    ReportPeriodType,
} from './types';

type Props = {
    period: { year: number; month: number; type: ReportPeriodType };
    selection: EmployeeSelection;
    selectedEmployeeCount: number;
    employees: Paginated<PayrollReportEmployee>;
    filters: PayrollReportFilters;
    filterOptions: PayrollReportFilterOptions;
    movements: PeriodMovementsReport;
};

type MovementTab = keyof PeriodMovementsReport;

function monthValue(year: number, month: number): string {
    return `${year}-${String(month).padStart(2, '0')}`;
}

function filenameFrom(response: Response): string {
    const match = /filename="?([^"]+)"?/.exec(
        response.headers.get('content-disposition') ?? '',
    );

    return match?.[1] ?? 'export';
}

function EmployeeMovementsTable({
    rows,
    dateLabel,
    emptyLabel,
}: {
    rows: MovementsEmployeeRow[];
    dateLabel: string;
    emptyLabel: string;
}) {
    const { t } = useTranslations();

    if (rows.length === 0) {
        return (
            <p className="p-6 text-center text-sm text-muted-foreground">
                {emptyLabel}
            </p>
        );
    }

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>{t('ui.payroll_reports.movements.columns.employee')}</TableHead>
                    <TableHead>{t('ui.payroll_reports.movements.columns.rut')}</TableHead>
                    <TableHead>{t('ui.payroll_reports.movements.columns.position')}</TableHead>
                    <TableHead>{t('ui.payroll_reports.movements.columns.premise')}</TableHead>
                    <TableHead>{dateLabel}</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row, index) => (
                    <TableRow key={`${row.employee}-${index}`}>
                        <TableCell className="font-medium">{row.employee}</TableCell>
                        <TableCell>{row.rut ?? '—'}</TableCell>
                        <TableCell>{row.position ?? '—'}</TableCell>
                        <TableCell>{row.premise ?? '—'}</TableCell>
                        <TableCell>{row.date}</TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

function LeaveMovementsTable({
    rows,
    emptyLabel,
}: {
    rows: MovementsLeaveRow[];
    emptyLabel: string;
}) {
    const { t } = useTranslations();

    if (rows.length === 0) {
        return (
            <p className="p-6 text-center text-sm text-muted-foreground">
                {emptyLabel}
            </p>
        );
    }

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>{t('ui.payroll_reports.movements.columns.employee')}</TableHead>
                    <TableHead>{t('ui.payroll_reports.movements.columns.rut')}</TableHead>
                    <TableHead>{t('ui.payroll_reports.movements.columns.type')}</TableHead>
                    <TableHead>{t('ui.payroll_reports.movements.columns.start_date')}</TableHead>
                    <TableHead>{t('ui.payroll_reports.movements.columns.end_date')}</TableHead>
                    <TableHead className="text-right">
                        {t('ui.payroll_reports.movements.columns.days')}
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row, index) => (
                    <TableRow key={`${row.employee}-${index}`}>
                        <TableCell className="font-medium">{row.employee}</TableCell>
                        <TableCell>{row.rut ?? '—'}</TableCell>
                        <TableCell>{row.type}</TableCell>
                        <TableCell>{row.startDate}</TableCell>
                        <TableCell>{row.endDate}</TableCell>
                        <TableCell className="text-right tabular-nums">{row.days}</TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

function ShiftChangesTable({ blocks }: { blocks: MovementsShiftChangeBlock[] }) {
    const { t } = useTranslations();

    const withChanges = blocks.filter((block) => block.rows.length > 0);

    if (withChanges.length === 0) {
        return (
            <p className="p-6 text-center text-sm text-muted-foreground">
                {t('ui.dt.reports.shift-changes.no_workers')}
            </p>
        );
    }

    return (
        <div className="divide-y">
            {withChanges.map((block) => (
                <div key={block.employee} className="space-y-2 p-4">
                    <div className="text-sm font-medium">{block.employee}</div>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('ui.dt.reports.shift-changes.columns.old_start_date')}</TableHead>
                                <TableHead>{t('ui.dt.reports.shift-changes.columns.old_shift')}</TableHead>
                                <TableHead>{t('ui.dt.reports.shift-changes.columns.new_start_date')}</TableHead>
                                <TableHead>{t('ui.dt.reports.shift-changes.columns.new_shift')}</TableHead>
                                <TableHead>{t('ui.dt.reports.shift-changes.columns.requested_by')}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {block.rows.map((row, index) => (
                                <TableRow key={index}>
                                    <TableCell>{row.oldStartDate ?? '–'}</TableCell>
                                    <TableCell>{row.oldShift ?? '–'}</TableCell>
                                    <TableCell>{row.newStartDate}</TableCell>
                                    <TableCell>{row.newShift}</TableCell>
                                    <TableCell>
                                        {t(`ui.dt.reports.shift-changes.requested_by.${row.requestedBy}`)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            ))}
        </div>
    );
}

export default function PeriodMovementsReport({
    period,
    selection: initialSelection,
    selectedEmployeeCount,
    employees,
    filters,
    filterOptions,
    movements,
}: Props) {
    const { t } = useTranslations();

    const [month, setMonth] = useState(monthValue(period.year, period.month));
    const [selection, setSelection] = useState<EmployeeSelection>(initialSelection);
    const [pendingExport, setPendingExport] = useState(false);
    const [tab, setTab] = useState<MovementTab>('hires');

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
        router.get(payrollReportRoutes.periodMovements().url, generationParams(), {
            preserveScroll: true,
        });
    };

    const handleExport = async () => {
        setPendingExport(true);

        try {
            const response = await fetch(
                payrollReportRoutes.periodMovements.export('excel', {
                    query: generationParams(),
                }).url,
            );

            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filenameFrom(response);
            link.click();
            URL.revokeObjectURL(url);
        } finally {
            setPendingExport(false);
        }
    };

    const tabs: { value: MovementTab; label: string; count: number }[] = [
        { value: 'hires', label: t('ui.payroll_reports.movements.tabs.hires'), count: movements.hires.length },
        { value: 'terminations', label: t('ui.payroll_reports.movements.tabs.terminations'), count: movements.terminations.length },
        { value: 'leaveStarts', label: t('ui.payroll_reports.movements.tabs.leave_starts'), count: movements.leaveStarts.length },
        { value: 'leaveEnds', label: t('ui.payroll_reports.movements.tabs.leave_ends'), count: movements.leaveEnds.length },
        { value: 'vacations', label: t('ui.payroll_reports.movements.tabs.vacations'), count: movements.vacations.length },
        { value: 'shiftChanges', label: t('ui.payroll_reports.movements.tabs.shift_changes'), count: movements.shiftChanges.filter((b) => b.rows.length > 0).length },
    ];

    return (
        <>
            <Head title={t('ui.payroll_reports.types.period-movements')} />

            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={t('ui.payroll_reports.types.period-movements')}
                        description={t('ui.payroll_reports.descriptions.period-movements')}
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
                            routeUrl={payrollReportRoutes.periodMovements().url}
                            selection={selection}
                            onSelectionChange={setSelection}
                        />
                    </CardContent>
                    <div className="flex items-center justify-between gap-4 border-t bg-muted/40 px-6 py-4">
                        <span className="text-sm">
                            {t('ui.payroll_reports.movements.selection_footer', {
                                count: String(selectedEmployeeCount),
                            })}
                        </span>
                        <Button type="button" onClick={handleGenerate}>
                            {t('ui.payroll_reports.movements.generate')}
                        </Button>
                    </div>
                </Card>

                {selectedEmployeeCount === 0 ? (
                    <Card>
                        <CardContent className="py-16 text-center text-sm text-muted-foreground">
                            {t('ui.payroll_reports.movements.no_employees')}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        <div className="flex items-center justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={pendingExport}
                                onClick={handleExport}
                            >
                                <FileSpreadsheet className="size-4" />
                                {t('ui.payroll_reports.movements.export.excel')}
                            </Button>
                        </div>

                        <Card className="gap-0 overflow-hidden py-0">
                            <CardContent className="space-y-4 p-4">
                                <Tabs value={tab} onValueChange={(value) => setTab(value as MovementTab)}>
                                    <TabsList>
                                        {tabs.map(({ value, label, count }) => (
                                            <TabsTrigger key={value} value={value}>
                                                {label}
                                                <Badge variant="secondary">{count}</Badge>
                                            </TabsTrigger>
                                        ))}
                                    </TabsList>

                                    <TabsContent value="hires">
                                        <EmployeeMovementsTable
                                            rows={movements.hires}
                                            dateLabel={t('ui.payroll_reports.movements.columns.hire_date')}
                                            emptyLabel={t('ui.payroll_reports.movements.empty.hires')}
                                        />
                                    </TabsContent>
                                    <TabsContent value="terminations">
                                        <EmployeeMovementsTable
                                            rows={movements.terminations}
                                            dateLabel={t('ui.payroll_reports.movements.columns.termination_date')}
                                            emptyLabel={t('ui.payroll_reports.movements.empty.terminations')}
                                        />
                                    </TabsContent>
                                    <TabsContent value="leaveStarts">
                                        <LeaveMovementsTable
                                            rows={movements.leaveStarts}
                                            emptyLabel={t('ui.payroll_reports.movements.empty.leave_starts')}
                                        />
                                    </TabsContent>
                                    <TabsContent value="leaveEnds">
                                        <LeaveMovementsTable
                                            rows={movements.leaveEnds}
                                            emptyLabel={t('ui.payroll_reports.movements.empty.leave_ends')}
                                        />
                                    </TabsContent>
                                    <TabsContent value="vacations">
                                        <LeaveMovementsTable
                                            rows={movements.vacations}
                                            emptyLabel={t('ui.payroll_reports.movements.empty.vacations')}
                                        />
                                    </TabsContent>
                                    <TabsContent value="shiftChanges">
                                        <ShiftChangesTable blocks={movements.shiftChanges} />
                                    </TabsContent>
                                </Tabs>
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </>
    );
}
