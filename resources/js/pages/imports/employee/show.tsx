import { Head } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { useTranslations } from '@/hooks/use-translations';
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
    importRun: ImportRun;
    schemaFields: SchemaField[];
};

/**
 * The wizard shell's status-driven step display (KOL-94.5): which step
 * renders is entirely a function of ImportRun's status. Upload (KOL-98),
 * mapping review (KOL-99), strategy (KOL-100), preview (KOL-101), and the
 * commit result (KOL-102) are all reachable today.
 */
export default function ShowEmployeeImport({ importRun, schemaFields }: Props) {
    const { t } = useTranslations();

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

                <div
                    className={
                        isEditable && step === 'mapping'
                            ? 'max-w-5xl'
                            : 'max-w-3xl'
                    }
                >
                    {isEditable ? (
                        step === 'mapping' ? (
                            <MappingReviewStep
                                importRunId={importRun.id}
                                originalFilename={importRun.original_filename}
                                columnMapping={importRun.column_mapping}
                                schemaFields={schemaFields}
                                onSaved={() => setStep('strategy')}
                            />
                        ) : step === 'strategy' ? (
                            <StrategyStep
                                importRunId={importRun.id}
                                strategy={importRun.strategy}
                                matchKey={importRun.match_key}
                                schemaFields={schemaFields}
                                onBack={() => setStep('mapping')}
                                onSaved={() => setStep('preview')}
                            />
                        ) : (
                            <PreviewStep
                                importRunId={importRun.id}
                                previewCounts={importRun.preview_counts}
                                onBack={() => setStep('strategy')}
                            />
                        )
                    ) : importRun.status === 'processing' ||
                      importRun.status === 'completed' ||
                      importRun.status === 'failed' ? (
                        <ResultStep
                            importRunId={importRun.id}
                            status={importRun.status}
                            createdCount={importRun.created_count}
                            updatedCount={importRun.updated_count}
                            skippedCount={importRun.skipped_count}
                            erroredCount={importRun.errored_count}
                        />
                    ) : null}
                </div>
            </div>
        </>
    );
}
