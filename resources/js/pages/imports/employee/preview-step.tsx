import { useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2 } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslations } from '@/hooks/use-translations';
import { store as runPreview } from '@/routes/imports/preview';

type PreviewCounts = {
    ready: number;
    warning: number;
    error: number;
    skipped: number;
};

type Props = {
    importRunId: number;
    previewCounts: PreviewCounts | null;
    onBack: () => void;
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
 * The Employee import wizard's preview step (KOL-101): only ever shows the
 * aggregate Ready/Warning/Error/Skipped counts persisted on ImportRun —
 * there is no per-row grid to show, server or client side. Before the
 * counts exist yet (run.status is still MappingReview), this renders the
 * "run preview" call to action instead; POSTing there flips the run to
 * PreviewReady and this same component re-renders with the counts.
 */
export function PreviewStep({ importRunId, previewCounts, onBack }: Props) {
    const { t } = useTranslations();
    const { post, processing, errors } = useForm<Record<string, never>>({});

    function handleRunPreview() {
        post(runPreview(importRunId).url, { preserveScroll: true });
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
                    <Button onClick={handleRunPreview} disabled={processing}>
                        {t('ui.employees.import.preview.run_submit')}
                    </Button>
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

            <div className="flex justify-start">
                <Button variant="outline" onClick={onBack}>
                    {t('ui.employees.import.preview.back')}
                </Button>
            </div>
        </div>
    );
}
