import { Head } from '@inertiajs/react';
import DocumentForm from '@/components/document-form';
import type { DocumentFormOptions } from '@/components/document-form';
import Heading from '@/components/heading';
import { useTranslations } from '@/hooks/use-translations';
import { update } from '@/routes/documents';

type Props = {
    document: {
        id: number;
        title: string;
        type: string;
        user_id: string;
        body: string;
        legal_rep_signatories: string;
        ordered_signing: boolean;
    };
    options: DocumentFormOptions;
};

export default function EditDocument({ document, options }: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.documents.edit.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.documents.edit.title')}
                    description={t('ui.documents.edit.description')}
                />

                <DocumentForm
                    method="patch"
                    action={update(document.id).url}
                    submitLabel={t('ui.documents.edit.submit')}
                    options={options}
                    initial={{
                        title: document.title,
                        type: document.type,
                        user_id: document.user_id,
                        body: document.body,
                        legal_rep_signatories: document.legal_rep_signatories,
                        ordered_signing: document.ordered_signing,
                    }}
                />
            </div>
        </>
    );
}
