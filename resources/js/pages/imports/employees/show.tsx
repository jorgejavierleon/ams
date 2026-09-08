import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { useTranslations } from '@/hooks/use-translations';
import { destroy } from '@/routes/imports';
import type { Paginated } from '@/types/ui';
import { MappingReviewStep } from './mapping-review-step';
import { PreviewStep } from './preview-step';
import { ResultStep } from './result-step';
import { StrategyStep } from './strategy-step';

type ColumnMapping = {
    sourceColumnIndex: number;
    sourceHeaderLabel: string | null;
    targetField: string | null;
    status: 'mapped' | 'unmapped' | 'ignored';
};

type SchemaField = {
    name: string;
    label: string;
    requiredForCreateOnly: boolean;
    isMatchKeyEligible: boolean;
};

type PreviewCounts = {
    ready: number;
    warning: number;
    error: number;
    skipped: number;
};

export type ImportRowIssue = {
    id: number;
    row: number;
    column: string;
    severity: string;
    message: string;
};

type ImportRun = {
    id: number;
    status: string;
    original_filename: string | null;
    column_mapping: ColumnMapping[];
    strategy: 'create_only' | 'update_only' | 'create_and_update' | null;
    match_key: string | null;
    preview_counts: PreviewCounts | null;
    created_count: number;
    updated_count: number;
    skipped_count: number;
    errored_count: number;
};

type Props = {
    resourceType: string;
    importRun: ImportRun;
    schemaFields: SchemaField[];
    issues: Paginated<ImportRowIssue> | null;
};

/**
 * The wizard shell's status-driven step display (KOL-94.5): which step
 * renders is entirely a function of ImportRun's status. Upload (KOL-98),
 * mapping review (KOL-99), strategy (KOL-100), preview (KOL-101), and the
 * commit result (KOL-102) are all reachable today.
 */
export default function ShowEmployeeImport({
    resourceType,
    importRun,
    schemaFields,
    issues,
}: Props) {
    const { t } = useTranslations();
    const [confirmingCancel, setConfirmingCancel] = useState(false);
    const [cancelling, setCancelling] = useState(false);

    function confirmCancel() {
        setCancelling(true);
        router.delete(destroy({ resourceType, importRun: importRun.id }).url, {
            onFinish: () => {
                setCancelling(false);
                setConfirmingCancel(false);
            },
        });
    }

    // MappingReview and PreviewReady share the same three-step client-only
    // sub-flow (mapping/strategy/preview) — there's no separate server
    // status for each of those (KOL-100, KOL-101), so which one renders
    // isn't derived from the server at all. Resubmitting mapping/strategy
    // while PreviewReady demotes the run back to MappingReview server-side
    // (KOL-101 AC #3) without needing this local state to change: the user
    // is already looking at the mapping/strategy step when that happens.
    // Landing straight on 'preview' when a run already has preview_counts
    // avoids re-showing steps the user already finished on every visit.
    const [step, setStep] = useState<'mapping' | 'strategy' | 'preview'>(
        importRun.status === 'preview_ready'
            ? 'preview'
            : importRun.strategy
              ? 'strategy'
              : 'mapping',
    );

    const isEditable =
        importRun.status === 'mapping_review' ||
        importRun.status === 'preview_ready';

    return (
        <>
            <Head title={t('ui.employees.import.title')} />

            <div className="space-y-6 p-6">
                <Heading title={t('ui.employees.import.title')} />

                <ConfirmDialog
                    open={confirmingCancel}
                    onOpenChange={setConfirmingCancel}
                    title={t('ui.employees.import.cancel.dialog_title')}
                    description={t(
                        'ui.employees.import.cancel.dialog_description',
                    )}
                    confirmLabel={t('ui.employees.import.cancel.confirm')}
                    onConfirm={confirmCancel}
                    processing={cancelling}
                />

                {isEditable ? (
                    step === 'mapping' ? (
                        <MappingReviewStep
                            resourceType={resourceType}
                            importRunId={importRun.id}
                            originalFilename={importRun.original_filename}
                            columnMapping={importRun.column_mapping}
                            schemaFields={schemaFields}
                            onSaved={() => setStep('strategy')}
                            onCancel={() => setConfirmingCancel(true)}
                        />
                    ) : step === 'strategy' ? (
                        <StrategyStep
                            resourceType={resourceType}
                            importRunId={importRun.id}
                            strategy={importRun.strategy}
                            matchKey={importRun.match_key}
                            schemaFields={schemaFields}
                            onBack={() => setStep('mapping')}
                            onSaved={() => setStep('preview')}
                            onCancel={() => setConfirmingCancel(true)}
                        />
                    ) : (
                        <PreviewStep
                            resourceType={resourceType}
                            importRunId={importRun.id}
                            previewCounts={importRun.preview_counts}
                            issues={issues}
                            onBack={() => setStep('strategy')}
                            onCancel={() => setConfirmingCancel(true)}
                        />
                    )
                ) : importRun.status === 'processing' ||
                  importRun.status === 'completed' ||
                  importRun.status === 'failed' ? (
                    <ResultStep
                        resourceType={resourceType}
                        importRunId={importRun.id}
                        status={importRun.status}
                        createdCount={importRun.created_count}
                        updatedCount={importRun.updated_count}
                        skippedCount={importRun.skipped_count}
                        erroredCount={importRun.errored_count}
                    />
                ) : null}
            </div>
        </>
    );
}
