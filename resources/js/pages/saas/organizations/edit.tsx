import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import OrganizationForm from '@/components/organization-form';
import type { PlanOption } from '@/components/organization-form';
import { useTranslations } from '@/hooks/use-translations';
import { update } from '@/routes/saas/organizations';

type Organization = {
    id: number;
    name: string;
    slug: string;
    plan: string;
};

type Props = {
    organization: Organization;
    plans: PlanOption[];
};

export default function EditOrganization({ organization, plans }: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.organizations.edit.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.organizations.edit.title')}
                    description={organization.name}
                />

                <OrganizationForm
                    plans={plans}
                    method="patch"
                    action={update(organization.id).url}
                    submitLabel={t('ui.organizations.edit.submit')}
                    initial={{
                        name: organization.name,
                        slug: organization.slug,
                        plan: organization.plan,
                    }}
                />
            </div>
        </>
    );
}
