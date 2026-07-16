import { Head } from '@inertiajs/react';
import DocumentVarForm from '@/components/document-var-form';
import Heading from '@/components/heading';
import { useTranslations } from '@/hooks/use-translations';
import { update } from '@/routes/saas/document-variables';

type DocumentVariable = {
    id: number;
    name: string;
    key: string;
    description: string | null;
};

type Props = {
    variable: DocumentVariable;
};

export default function EditDocumentVariable({ variable }: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.document_variables.edit.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.document_variables.edit.title')}
                    description={variable.name}
                />

                <DocumentVarForm
                    method="patch"
                    action={update(variable.id).url}
                    submitLabel={t('ui.document_variables.edit.submit')}
                    initial={{
                        name: variable.name,
                        key: variable.key,
                        description: variable.description ?? '',
                    }}
                />
            </div>
        </>
    );
}
