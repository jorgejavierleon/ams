import { usePoll } from '@inertiajs/react';
import { CheckCircle2, XCircle } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';

type Props = {
    status: 'processing' | 'completed' | 'failed';
    createdCount: number;
    updatedCount: number;
    skippedCount: number;
    erroredCount: number;
};

function StatTile({
    label,
    value,
    tone,
}: {
    label: string;
    value: number;
    tone: 'default' | 'error';
}) {
    return (
        <Card>
            <CardContent className="space-y-1">
                <p className="text-sm text-muted-foreground">{label}</p>
                <p
                    className={
                        tone === 'error'
                            ? 'text-2xl font-semibold text-destructive'
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
 * Only rendered while the run is Processing (KOL-102 AC #7) — polling starts
 * on mount and stops automatically once the parent switches away from this
 * branch (Completed/Failed) and unmounts it, per Inertia's usePoll
 * lifecycle. `only: ['importRun']` keeps every tick a lightweight partial
 * reload of the same show() props this page already renders from.
 */
function ProcessingView() {
    const { t } = useTranslations();

    usePoll(2000, { only: ['importRun'] });

    return (
        <Card>
            <CardContent className="flex items-center gap-4 py-8">
                <Spinner className="size-5" />
                <div>
                    <p className="font-medium">
                        {t('ui.employees.import.result.processing_title')}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'ui.employees.import.result.processing_description',
                        )}
                    </p>
                </div>
            </CardContent>
        </Card>
    );
}

/**
 * The Employee import wizard's terminal step (KOL-102): whatever status
 * ProcessImportRun leaves the run in once commit finishes — Processing while
 * the job runs, Completed with the final counts, or Failed with no retry
 * action (AC #7), matching ProcessImportRun's own single-attempt-per-dispatch
 * contract.
 */
export function ResultStep({
    status,
    createdCount,
    updatedCount,
    skippedCount,
    erroredCount,
}: Props) {
    const { t } = useTranslations();

    if (status === 'processing') {
        return <ProcessingView />;
    }

    if (status === 'failed') {
        return (
            <Alert variant="destructive">
                <XCircle className="size-4" />
                <AlertTitle>
                    {t('ui.employees.import.result.failed_title')}
                </AlertTitle>
                <AlertDescription>
                    {t('ui.employees.import.result.failed_description')}
                </AlertDescription>
            </Alert>
        );
    }

    const total = createdCount + updatedCount + skippedCount + erroredCount;

    return (
        <div className="space-y-6">
            <div className="flex items-center gap-2 text-emerald-700 dark:text-emerald-400">
                <CheckCircle2 className="size-5 shrink-0" />
                <div>
                    <p className="font-medium">
                        {t('ui.employees.import.result.completed_title')}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'ui.employees.import.result.completed_description',
                            { total },
                        )}
                    </p>
                </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-4">
                <StatTile
                    label={t('ui.employees.import.result.counts.created')}
                    value={createdCount}
                    tone="default"
                />
                <StatTile
                    label={t('ui.employees.import.result.counts.updated')}
                    value={updatedCount}
                    tone="default"
                />
                <StatTile
                    label={t('ui.employees.import.result.counts.skipped')}
                    value={skippedCount}
                    tone="default"
                />
                <StatTile
                    label={t('ui.employees.import.result.counts.errored')}
                    value={erroredCount}
                    tone="error"
                />
            </div>
        </div>
    );
}
