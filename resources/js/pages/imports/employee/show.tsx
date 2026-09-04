import { Head } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslations } from '@/hooks/use-translations';
import { MappingReviewStep } from './mapping-review-step';
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

type ImportRun = {
    id: number;
    status: string;
    original_filename: string | null;
    column_mapping: ColumnMapping[];
    strategy: 'create_only' | 'update_only' | 'create_and_update' | null;
    match_key: string | null;
};

type Props = {
    importRun: ImportRun;
    schemaFields: SchemaField[];
};

/**
 * The wizard shell's status-driven step display (KOL-94.5): which step
 * renders is entirely a function of ImportRun's status. Upload (KOL-98) and
 * mapping review (KOL-99) are reachable today; strategy/preview/commit
 * belong to steps this wizard doesn't have yet (KOL-100..102).
 */
export default function ShowEmployeeImport({ importRun, schemaFields }: Props) {
    const { t } = useTranslations();

    // A client-only sub-step: mapping and strategy both happen while the
    // run's own status stays MappingReview (there's no separate status for
    // strategy, KOL-100), so which one renders isn't derived from the
    // server at all. Landing on 'strategy' when a strategy is already saved
    // avoids re-showing a review the user already finished on every visit.
    const [step, setStep] = useState<'mapping' | 'strategy'>(
        importRun.strategy ? 'strategy' : 'mapping',
    );

    return (
        <>
            <Head title={t('ui.employees.import.title')} />

            <div className="space-y-6 p-6">
                <Heading title={t('ui.employees.import.title')} />

                <div
                    className={
                        importRun.status === 'mapping_review'
                            ? 'max-w-5xl'
                            : 'max-w-3xl'
                    }
                >
                    {importRun.status === 'mapping_review' ? (
                        step === 'mapping' ? (
                            <MappingReviewStep
                                importRunId={importRun.id}
                                originalFilename={importRun.original_filename}
                                columnMapping={importRun.column_mapping}
                                schemaFields={schemaFields}
                                onSaved={() => setStep('strategy')}
                            />
                        ) : (
                            <StrategyStep
                                importRunId={importRun.id}
                                strategy={importRun.strategy}
                                matchKey={importRun.match_key}
                                schemaFields={schemaFields}
                                onBack={() => setStep('mapping')}
                            />
                        )
                    ) : (
                        <Card>
                            <CardContent>
                                <p className="text-sm text-muted-foreground">
                                    {importRun.status}
                                </p>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}
