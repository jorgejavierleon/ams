import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import Heading from '@/components/heading';
import OrganizationForm from '@/components/organization-form';
import type { PlanOption } from '@/components/organization-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import organizations, { update } from '@/routes/saas/organizations';

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

type EmailLimits = {
    baseline: number | null;
    activeUsersCount: number;
    defaultLimit: number;
    softLimit: number;
    hardLimit: number;
    softOverride: number | null;
    hardOverride: number | null;
};

type Props = {
    organization: Organization;
    plans: PlanOption[];
    emailVolume: EmailVolume;
    emailLimits: EmailLimits;
};

export default function EditOrganization({
    organization,
    plans,
    emailVolume,
    emailLimits,
}: Props) {
    const { t } = useTranslations();

    const emailLimitsForm = useForm({
        soft_limit_override: emailLimits.softOverride?.toString() ?? '',
        hard_limit_override: emailLimits.hardOverride?.toString() ?? '',
    });

    function submitEmailLimits(event: FormEvent) {
        event.preventDefault();
        emailLimitsForm.patch(organizations.emailLimits.update(organization.id).url, {
            preserveScroll: true,
        });
    }

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

                <div className="flex flex-col gap-6 sm:flex-row">
                    <Card className="sm:w-80 sm:flex-none">
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
                                    {t(
                                        'ui.organizations.email_volume.current_month',
                                    )}
                                </p>
                            </div>
                            <div>
                                <p className="text-2xl font-semibold tabular-nums">
                                    {emailVolume.previousMonth}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {t(
                                        'ui.organizations.email_volume.previous_month',
                                    )}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="flex-1">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {t('ui.organizations.email_limits.title')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="mb-4 text-xs text-muted-foreground">
                                {t(
                                    'ui.organizations.email_limits.default_hint',
                                    {
                                        count: emailLimits.activeUsersCount,
                                        baseline: emailLimits.baseline ?? 0,
                                        limit: emailLimits.defaultLimit,
                                    },
                                )}
                            </p>

                            <form
                                onSubmit={submitEmailLimits}
                                className="grid gap-4"
                            >
                                <div className="grid gap-4 sm:max-w-md sm:grid-cols-2">
                                    <FormField
                                        label={t(
                                            'ui.organizations.email_limits.fields.soft_limit_override',
                                        )}
                                        htmlFor="soft_limit_override"
                                        error={
                                            emailLimitsForm.errors
                                                .soft_limit_override
                                        }
                                    >
                                        <Input
                                            id="soft_limit_override"
                                            type="number"
                                            min={1}
                                            placeholder={String(
                                                emailLimits.defaultLimit,
                                            )}
                                            value={
                                                emailLimitsForm.data
                                                    .soft_limit_override
                                            }
                                            onChange={(e) =>
                                                emailLimitsForm.setData(
                                                    'soft_limit_override',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </FormField>

                                    <FormField
                                        label={t(
                                            'ui.organizations.email_limits.fields.hard_limit_override',
                                        )}
                                        htmlFor="hard_limit_override"
                                        error={
                                            emailLimitsForm.errors
                                                .hard_limit_override
                                        }
                                    >
                                        <Input
                                            id="hard_limit_override"
                                            type="number"
                                            min={1}
                                            placeholder={String(
                                                emailLimits.defaultLimit,
                                            )}
                                            value={
                                                emailLimitsForm.data
                                                    .hard_limit_override
                                            }
                                            onChange={(e) =>
                                                emailLimitsForm.setData(
                                                    'hard_limit_override',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </FormField>
                                </div>

                                <p className="text-xs text-muted-foreground">
                                    {t(
                                        'ui.organizations.email_limits.override_hint',
                                    )}
                                </p>

                                <div>
                                    <Button
                                        type="submit"
                                        disabled={emailLimitsForm.processing}
                                    >
                                        {emailLimitsForm.processing && (
                                            <Spinner />
                                        )}
                                        {t(
                                            'ui.organizations.email_limits.submit',
                                        )}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
