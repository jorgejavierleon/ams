import { Head } from '@inertiajs/react';
import DocumentTemplateForm from '@/components/document-template-form';
import type {
    DocumentTemplateFormData,
    DocumentTemplateFormOptions,
} from '@/components/document-template-form';
import Heading from '@/components/heading';
import { useTranslations } from '@/hooks/use-translations';
import { store } from '@/routes/document-templates';

type Props = {
    options: DocumentTemplateFormOptions;
};

const emptyTemplate: DocumentTemplateFormData = {
    title: '',
    type: '',
    body: '',
};

export default function CreateDocumentTemplate({ options }: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.document_templates.create.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.document_templates.create.title')}
                    description={t('ui.document_templates.create.description')}
                />

                <DocumentTemplateForm
                    method="post"
                    action={store().url}
                    submitLabel={t('ui.document_templates.create.submit')}
                    options={options}
                    initial={emptyTemplate}
                />
            </div>
        </>
    );
}
