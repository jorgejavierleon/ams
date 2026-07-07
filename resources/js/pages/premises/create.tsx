import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import PremiseForm from '@/components/premise-form';
import type { Option } from '@/components/premise-form';
import { useTranslations } from '@/hooks/use-translations';
import { store } from '@/routes/premises';

type Props = {
    companies: Option[];
};

export default function CreatePremise({ companies }: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.premises.create.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.premises.create.title')}
                    description={t('ui.premises.create.description')}
                />

                <PremiseForm
                    companies={companies}
                    method="post"
                    action={store().url}
                    submitLabel={t('ui.premises.create.submit')}
                />
            </div>
        </>
    );
}
