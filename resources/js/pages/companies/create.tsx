import { Head } from '@inertiajs/react';
import CompanyForm from '@/components/company-form';
import type { Option } from '@/components/company-form';
import Heading from '@/components/heading';
import { useTranslations } from '@/hooks/use-translations';
import { store } from '@/routes/companies';

type Props = {
    regions: Option[];
};

export default function CreateCompany({ regions }: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.companies.create.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.companies.create.title')}
                    description={t('ui.companies.create.description')}
                />

                <CompanyForm
                    regions={regions}
                    method="post"
                    action={store().url}
                    submitLabel={t('ui.companies.create.submit')}
                />
            </div>
        </>
    );
}
