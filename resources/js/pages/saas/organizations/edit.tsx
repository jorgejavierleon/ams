import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import OrganizationForm from '@/components/organization-form';
import type { PlanOption } from '@/components/organization-form';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslations } from '@/hooks/use-translations';
import { update } from '@/routes/saas/organizations';

type Organization = {
    id: number;
    name: string;
    slug: string;
    plan: string;
};

type EmailVolume = {
    currentMonth: number;
    previousMonth: number;
};

type Props = {
    organization: Organization;
    plans: PlanOption[];
    emailVolume: EmailVolume;
};

export default function EditOrganization({ organization, plans, emailVolume }: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.organizations.edit.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.organizations.edit.title')}
                    description={organization.name}
                />

                <Card>
                    <CardContent>
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
                    </CardContent>
                </Card>

                <Card className="max-w-sm">
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            {t('ui.organizations.email_volume.title')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex gap-8">
                        <div>
                            <p className="text-2xl font-semibold tabular-nums">
                                {emailVolume.currentMonth}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {t('ui.organizations.email_volume.current_month')}
                            </p>
                        </div>
                        <div>
                            <p className="text-2xl font-semibold tabular-nums">
                                {emailVolume.previousMonth}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {t('ui.organizations.email_volume.previous_month')}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
