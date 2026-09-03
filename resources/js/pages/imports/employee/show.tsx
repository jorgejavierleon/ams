import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslations } from '@/hooks/use-translations';

type ImportRun = {
    id: number;
    status: string;
    original_filename: string | null;
    column_count: number;
};

type Props = {
    importRun: ImportRun;
};

/**
 * The wizard shell's status-driven step display (KOL-94.5): which step
 * renders is entirely a function of ImportRun's status. Only MappingReview
 * is reachable today — store() (KOL-98) always finishes synchronously, and
 * every later status belongs to a step this wizard doesn't have yet
 * (mapping review is KOL-99, strategy/preview/commit are KOL-100..102).
 */
export default function ShowEmployeeImport({ importRun }: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.employees.import.title')} />

            <div className="space-y-6 p-6">
                <Heading title={t('ui.employees.import.title')} />

                <div className="max-w-3xl">
                    {importRun.status === 'mapping_review' ? (
                        <Card>
                            <CardContent className="space-y-2">
                                <h2 className="text-lg font-medium">
                                    {t(
                                        'ui.employees.import.show.mapping_review_title',
                                    )}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'ui.employees.import.show.mapping_review_description',
                                        {
                                            count: importRun.column_count,
                                            filename:
                                                importRun.original_filename ??
                                                '',
                                        },
                                    )}
                                </p>
                            </CardContent>
                        </Card>
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
