import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import OrganizationForm from '@/components/organization-form';
import type { PlanOption } from '@/components/organization-form';
import { useTranslations } from '@/hooks/use-translations';
import { store } from '@/routes/saas/organizations';

type Props = {
    plans: PlanOption[];
};

export default function CreateOrganization({ plans }: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.organizations.create.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.organizations.create.title')}
                    description={t('ui.organizations.create.description')}
                />

                <OrganizationForm
                    plans={plans}
                    method="post"
                    action={store().url}
                    submitLabel={t('ui.organizations.create.submit')}
                />
            </div>
        </>
    );
}
