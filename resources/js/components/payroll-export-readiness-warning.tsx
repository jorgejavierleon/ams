import { Info, TriangleAlert } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Checkbox } from '@/components/ui/checkbox';
import { useTranslations } from '@/hooks/use-translations';

export type PayrollExportFinding = {
    type:
        | 'pending_mark_modification'
        | 'irregular_workday'
        | 'incomplete_workday'
        | 'open_incident';
    employee_id: number | null;
    employee_name: string | null;
    date: string | null;
    reason: string;
    resolution_url: string | null;
    blocking: boolean;
};

type PayrollExportReadinessWarningProps = {
    findings: PayrollExportFinding[];
    confirmed: boolean;
    onConfirmedChange: (confirmed: boolean) => void;
};

/**
 * Blocks a payroll export screen from proceeding silently over unresolved
 * attendance data (KOL-14, PRD RF-2): lists every finding grouped by
 * employee, plus period-level context (open incidents), and exposes a
 * `confirmed` flag the caller gates its own export action on. Renders
 * nothing for a clean selection — no warning, no extra step.
 */
export function PayrollExportReadinessWarning({
    findings,
    confirmed,
    onConfirmedChange,
}: PayrollExportReadinessWarningProps) {
    const { t } = useTranslations();

    if (findings.length === 0) {
        return null;
    }

    const blockingFindings = findings.filter((finding) => finding.blocking);
    const informationalFindings = findings.filter(
        (finding) => !finding.blocking,
    );
    const groupedByEmployee = groupByEmployee(blockingFindings);

    return (
        <Alert
            variant={blockingFindings.length > 0 ? 'destructive' : 'default'}
        >
            <TriangleAlert />
            <AlertTitle>{t('ui.payroll_export.warning.title')}</AlertTitle>
            <AlertDescription className="w-full">
                <p>{t('ui.payroll_export.warning.description')}</p>

                {groupedByEmployee.map(([employeeName, employeeFindings]) => (
                    <div key={employeeName} className="mt-2 w-full">
                        <p className="font-medium text-foreground">
                            {employeeName}
                        </p>
                        <ul className="list-inside list-disc">
                            {employeeFindings.map((finding, index) => (
                                <li key={index}>
                                    {finding.date ??
                                        t('ui.payroll_export.warning.no_date')}
                                    {' — '}
                                    {finding.reason}
                                    {finding.resolution_url && (
                                        <>
                                            {' '}
                                            <a
                                                href={finding.resolution_url}
                                                className="underline"
                                            >
                                                {t(
                                                    'ui.payroll_export.warning.resolve_link',
                                                )}
                                            </a>
                                        </>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}

                {informationalFindings.length > 0 && (
                    <div className="mt-2 flex w-full items-start gap-2 text-muted-foreground">
                        <Info className="mt-0.5 size-4 shrink-0" />
                        <div>
                            <p className="font-medium">
                                {t(
                                    'ui.payroll_export.warning.informational_title',
                                )}
                            </p>
                            <ul className="list-inside list-disc">
                                {informationalFindings.map((finding, index) => (
                                    <li key={index}>{finding.reason}</li>
                                ))}
                            </ul>
                        </div>
                    </div>
                )}

                {blockingFindings.length > 0 && (
                    <label className="mt-3 flex items-center gap-2 font-normal text-foreground">
                        <Checkbox
                            checked={confirmed}
                            onCheckedChange={(checked) =>
                                onConfirmedChange(checked === true)
                            }
                        />
                        {t('ui.payroll_export.warning.confirm_label')}
                    </label>
                )}
            </AlertDescription>
        </Alert>
    );
}

function groupByEmployee(
    findings: PayrollExportFinding[],
): [string, PayrollExportFinding[]][] {
    const groups = new Map<string, PayrollExportFinding[]>();

    for (const finding of findings) {
        const key = finding.employee_name ?? '';
        const group = groups.get(key) ?? [];
        group.push(finding);
        groups.set(key, group);
    }

    return Array.from(groups.entries());
}
