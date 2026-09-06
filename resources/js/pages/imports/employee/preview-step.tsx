import { useForm } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { AlertTriangle, CheckCircle2 } from 'lucide-react';
import { useMemo } from 'react';
import { DataTable } from '@/components/data-table';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslations } from '@/hooks/use-translations';
import { show as showImportRun } from '@/routes/imports';
import { store as commitImport } from '@/routes/imports/commit';
import { store as runPreview } from '@/routes/imports/preview';
import type { Paginated } from '@/types/ui';
import type { ImportRowIssue } from './show';

type PreviewCounts = {
    ready: number;
    warning: number;
    error: number;
    skipped: number;
};

type Props = {
    importRunId: number;
    previewCounts: PreviewCounts | null;
    issues: Paginated<ImportRowIssue> | null;
    onBack: () => void;
    onCancel: () => void;
};

function StatTile({
    label,
    value,
    tone,
}: {
    label: string;
    value: number;
    tone: 'default' | 'warning' | 'error';
}) {
    return (
        <Card>
            <CardContent className="space-y-1">
                <p className="text-sm text-muted-foreground">{label}</p>
                <p
                    className={
                        tone === 'error'
                            ? 'text-2xl font-semibold text-destructive'
                            : tone === 'warning'
                              ? 'text-2xl font-semibold text-amber-600 dark:text-amber-400'
                              : 'text-2xl font-semibold'
                    }
                >
                    {value}
                </p>
            </CardContent>
        </Card>
    );
}

/**
 * The Employee import wizard's preview step (KOL-101, KOL-111): shows the
 * aggregate Ready/Warning/Error/Skipped counts persisted on ImportRun, plus
 * a paginated per-row issue table once the preview found anything wrong
 * (KOL-111) — so a user can see exactly what to fix without downloading the
 * CSV or committing the import. Before the counts exist yet (run.status is
 * still MappingReview), this renders the "run preview" call to action
 * instead; POSTing there flips the run to PreviewReady and this same
 * component re-renders with the counts.
 */
export function PreviewStep({
    importRunId,
    previewCounts,
    issues,
    onBack,
    onCancel,
}: Props) {
    const { t } = useTranslations();
    const { post, processing, errors } = useForm<Record<string, never>>({});
    const { post: postCommit, processing: committing } = useForm<
        Record<string, never>
    >({});

    const issueColumns = useMemo<ColumnDef<ImportRowIssue>[]>(
        () => [
            {
                accessorKey: 'row',
                header: t('ui.employees.import.preview.issues.columns.row'),
                cell: ({ row }) => row.original.row,
            },
            {
                accessorKey: 'column',
                header: t('ui.employees.import.preview.issues.columns.column'),
                cell: ({ row }) => row.original.column || '—',
            },
            {
                accessorKey: 'severity',
                header: t(
                    'ui.employees.import.preview.issues.columns.severity',
                ),
                cell: ({ row }) =>
                    row.original.severity === 'Error' ? (
                        <span className="font-medium text-destructive">
                            {row.original.severity}
                        </span>
                    ) : (
                        <span className="font-medium text-amber-600 dark:text-amber-400">
                            {row.original.severity}
                        </span>
                    ),
            },
            {
                accessorKey: 'message',
                header: t('ui.employees.import.preview.issues.columns.message'),
                cell: ({ row }) => row.original.message,
            },
        ],
        [t],
    );

    function handleRunPreview() {
        post(runPreview(importRunId).url, { preserveScroll: true });
    }

    function handleCommit() {
        postCommit(commitImport(importRunId).url, { preserveScroll: true });
    }

    if (!previewCounts) {
        return (
            <div className="space-y-6">
                <Alert>
                    <AlertTitle>
                        {t('ui.employees.import.preview.run_title')}
                    </AlertTitle>
                    <AlertDescription>
                        {t('ui.employees.import.preview.run_description')}
                    </AlertDescription>
                </Alert>
                {errors.preview && (
                    <Alert variant="destructive">
                        <AlertDescription>{errors.preview}</AlertDescription>
                    </Alert>
                )}
                <div className="flex items-center justify-between">
                    <Button variant="outline" onClick={onBack}>
                        {t('ui.employees.import.preview.back')}
                    </Button>
                    <div className="flex items-center gap-3">
                        <Button variant="outline" onClick={onCancel}>
                            {t('ui.employees.import.cancel.button')}
                        </Button>
                        <Button
                            onClick={handleRunPreview}
                            disabled={processing}
                        >
                            {t('ui.employees.import.preview.run_submit')}
                        </Button>
                    </div>
                </div>
            </div>
        );
    }

    const total =
        previewCounts.ready +
        previewCounts.warning +
        previewCounts.error +
        previewCounts.skipped;

    return (
        <div className="space-y-6">
            <div className="grid gap-3 sm:grid-cols-4">
                <StatTile
                    label={t('ui.employees.import.preview.counts.ready')}
                    value={previewCounts.ready}
                    tone="default"
                />
                <StatTile
                    label={t('ui.employees.import.preview.counts.warning')}
                    value={previewCounts.warning}
                    tone="warning"
                />
                <StatTile
                    label={t('ui.employees.import.preview.counts.error')}
                    value={previewCounts.error}
                    tone="error"
                />
                <StatTile
                    label={t('ui.employees.import.preview.counts.skipped')}
                    value={previewCounts.skipped}
                    tone="default"
                />
            </div>

            {previewCounts.error > 0 ? (
                <Alert variant="destructive">
                    <AlertTriangle className="size-4" />
                    <AlertTitle>
                        {t('ui.employees.import.preview.has_errors_title', {
                            count: previewCounts.error,
                            total,
                        })}
                    </AlertTitle>
                    <AlertDescription>
                        {t(
                            'ui.employees.import.preview.has_errors_description',
                        )}
                    </AlertDescription>
                </Alert>
            ) : (
                <Alert>
                    <CheckCircle2 className="size-4" />
                    <AlertTitle>
                        {t('ui.employees.import.preview.no_errors_title')}
                    </AlertTitle>
                    <AlertDescription>
                        {previewCounts.warning > 0
                            ? t(
                                  'ui.employees.import.preview.no_errors_description_with_warnings',
                                  { count: previewCounts.warning },
                              )
                            : t(
                                  'ui.employees.import.preview.no_errors_description_clean',
                              )}
                    </AlertDescription>
                </Alert>
            )}

            {(previewCounts.error > 0 || previewCounts.warning > 0) &&
                issues && (
                    <div className="space-y-2">
                        <h2 className="text-sm font-medium">
                            {t('ui.employees.import.preview.issues.title')}
                        </h2>
                        <DataTable
                            data={issues}
                            columns={issueColumns}
                            routeUrl={showImportRun(importRunId).url}
                            only={['issues']}
                            getRowId={(issue) => String(issue.id)}
                            emptyLabel={t(
                                'ui.employees.import.preview.issues.empty',
                            )}
                        />
                    </div>
                )}

            <div className="flex items-center justify-between">
                <Button
                    variant="outline"
                    onClick={onBack}
                    disabled={committing}
                >
                    {t('ui.employees.import.preview.back')}
                </Button>
                <div className="flex items-center gap-3">
                    <Button
                        variant="outline"
                        onClick={onCancel}
                        disabled={committing}
                    >
                        {t('ui.employees.import.cancel.button')}
                    </Button>
                    <Button onClick={handleCommit} disabled={committing}>
                        {t('ui.employees.import.preview.confirm_submit')}
                    </Button>
                </div>
            </div>
        </div>
    );
}
