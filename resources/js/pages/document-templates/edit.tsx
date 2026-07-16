import { Head } from '@inertiajs/react';
import DocumentTemplateForm from '@/components/document-template-form';
import type { DocumentTemplateFormOptions } from '@/components/document-template-form';
import Heading from '@/components/heading';
import { useTranslations } from '@/hooks/use-translations';
import { update } from '@/routes/document-templates';

type Props = {
    template: {
        id: number;
        title: string;
        type: string;
        body: string;
    };
    options: DocumentTemplateFormOptions;
};

export default function EditDocumentTemplate({ template, options }: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.document_templates.edit.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.document_templates.edit.title')}
                    description={t('ui.document_templates.edit.description')}
                />

                <DocumentTemplateForm
                    method="patch"
                    action={update(template.id).url}
                    submitLabel={t('ui.document_templates.edit.submit')}
                    options={options}
                    initial={{
                        title: template.title,
                        type: template.type,
                        body: template.body,
                    }}
                />
            </div>
        </>
    );
}
