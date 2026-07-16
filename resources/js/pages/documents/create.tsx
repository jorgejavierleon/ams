import { Head } from '@inertiajs/react';
import DocumentForm from '@/components/document-form';
import type {
    DocumentFormData,
    DocumentFormOptions,
    DocumentTemplateOption,
} from '@/components/document-form';
import Heading from '@/components/heading';
import { useTranslations } from '@/hooks/use-translations';
import { store } from '@/routes/documents';

type Props = {
    options: DocumentFormOptions;
    templates: DocumentTemplateOption[];
};

const emptyDocument: DocumentFormData = {
    title: '',
    type: '',
    user_id: '',
    body: '',
    legal_rep_signatories: '0',
    ordered_signing: false,
};

export default function CreateDocument({ options, templates }: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.documents.create.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.documents.create.title')}
                    description={t('ui.documents.create.description')}
                />

                <DocumentForm
                    method="post"
                    action={store().url}
                    submitLabel={t('ui.documents.create.submit')}
                    options={options}
                    initial={emptyDocument}
                    templates={templates}
                />
            </div>
        </>
    );
}
