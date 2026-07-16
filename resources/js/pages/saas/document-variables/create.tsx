import { Head } from '@inertiajs/react';
import DocumentVarForm from '@/components/document-var-form';
import Heading from '@/components/heading';
import { useTranslations } from '@/hooks/use-translations';
import { store } from '@/routes/saas/document-variables';

export default function CreateDocumentVariable() {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.document_variables.create.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.document_variables.create.title')}
                    description={t('ui.document_variables.create.description')}
                />

                <DocumentVarForm
                    method="post"
                    action={store().url}
                    submitLabel={t('ui.document_variables.create.submit')}
                />
            </div>
        </>
    );
}
