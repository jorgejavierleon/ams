import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslations } from '@/hooks/use-translations';
import { MappingReviewStep } from './mapping-review-step';

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
};

type ImportRun = {
    id: number;
    status: string;
    original_filename: string | null;
    column_mapping: ColumnMapping[];
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
                        <MappingReviewStep
                            importRunId={importRun.id}
                            originalFilename={importRun.original_filename}
                            columnMapping={importRun.column_mapping}
                            schemaFields={schemaFields}
                        />
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
