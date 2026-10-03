import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, Ban } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { FormField } from '@/components/form-field';
import Heading from '@/components/heading';
import OrganizationForm from '@/components/organization-form';
import type { PlanOption } from '@/components/organization-form';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
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
    softLimitCrossedThisMonth: boolean;
    hardLimitCrossedThisMonth: boolean;
};

type EmailSending = {
    enabled: boolean;
    overridden: boolean;
};

type Props = {
    organization: Organization;
    plans: PlanOption[];
    emailVolume: EmailVolume;
    emailLimits: EmailLimits;
    emailSending: EmailSending;
};

export default function EditOrganization({
    organization,
    plans,
    emailVolume,
    emailLimits,
    emailSending,
}: Props) {
    const { t } = useTranslations();
    const [togglingEmailSending, setTogglingEmailSending] = useState(false);

    function toggleEmailSending() {
        setTogglingEmailSending(true);
        router.patch(
            organizations.emailSending.toggle(organization.id).url,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setTogglingEmailSending(false),
            },
        );
    }

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

                <div className="flex flex-col gap-6 sm:flex-row sm:flex-wrap">
                    <Card className="sm:w-72 sm:flex-none">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {t('ui.organizations.email_sending.title')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div className="flex items-center justify-between gap-4">
                                <span className="text-sm font-medium">
                                    {emailSending.enabled
                                        ? t(
                                              'ui.organizations.email_sending.enabled',
                                          )
                                        : t(
                                              'ui.organizations.email_sending.disabled',
                                          )}
                                </span>
                                <Switch
                                    checked={emailSending.enabled}
                                    disabled={togglingEmailSending}
                                    onCheckedChange={toggleEmailSending}
                                    aria-label={t(
                                        'ui.organizations.email_sending.title',
                                    )}
                                />
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {t('ui.organizations.email_sending.hint')}
                            </p>
                        </CardContent>
                    </Card>

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
                            {!emailSending.overridden &&
                                emailLimits.hardLimitCrossedThisMonth && (
                                    <Alert
                                        variant="destructive"
                                        className="mb-4"
                                    >
                                        <Ban />
                                        <AlertDescription>
                                            {t(
                                                'ui.organizations.email_limits.alerts.hard_crossed',
                                            )}
                                        </AlertDescription>
                                    </Alert>
                                )}

                            {!emailSending.overridden &&
                                !emailLimits.hardLimitCrossedThisMonth &&
                                emailLimits.softLimitCrossedThisMonth && (
                                    <Alert className="mb-4">
                                        <AlertTriangle />
                                        <AlertDescription>
                                            {t(
                                                'ui.organizations.email_limits.alerts.soft_crossed',
                                            )}
                                        </AlertDescription>
                                    </Alert>
                                )}

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
