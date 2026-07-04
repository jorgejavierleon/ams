import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { index } from '@/routes/saas/organizations';

export type PlanOption = { value: string; label: string };

type OrganizationFormData = {
    name: string;
    slug: string;
    plan: string;
};

type Props = {
    plans: PlanOption[];
    method: 'post' | 'patch';
    action: string;
    submitLabel: string;
    initial?: OrganizationFormData;
};

function slugify(value: string): string {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export default function OrganizationForm({
    plans,
    method,
    action,
    submitLabel,
    initial,
}: Props) {
    const { t } = useTranslations();
    const { data, setData, post, patch, processing, errors } =
        useForm<OrganizationFormData>({
            name: initial?.name ?? '',
            slug: initial?.slug ?? '',
            plan: initial?.plan ?? plans[0]?.value ?? '',
        });

    // On create, keep the slug in sync with the name until the user edits it.
    const [slugEdited, setSlugEdited] = useState(Boolean(initial));

    function handleNameChange(value: string) {
        setData('name', value);

        if (!slugEdited) {
            setData('slug', slugify(value));
        }
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        const options = { preserveScroll: true };

        if (method === 'patch') {
            patch(action, options);
        } else {
            post(action, options);
        }
    }

    return (
        <form onSubmit={submit} className="grid max-w-xl gap-6">
            <div className="grid gap-2">
                <Label htmlFor="name">{t('ui.organizations.form.name')}</Label>
                <Input
                    id="name"
                    value={data.name}
                    onChange={(e) => handleNameChange(e.target.value)}
                    required
                    autoFocus
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="slug">{t('ui.organizations.form.slug')}</Label>
                <Input
                    id="slug"
                    value={data.slug}
                    onChange={(e) => {
                        setSlugEdited(true);
                        setData('slug', e.target.value);
                    }}
                    required
                />
                <InputError message={errors.slug} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="plan">{t('ui.organizations.form.plan')}</Label>
                <Select
                    value={data.plan}
                    onValueChange={(value) => setData('plan', value)}
                >
                    <SelectTrigger id="plan">
                        <SelectValue
                            placeholder={t(
                                'ui.organizations.form.plan_placeholder',
                            )}
                        />
                    </SelectTrigger>
                    <SelectContent>
                        {plans.map((plan) => (
                            <SelectItem key={plan.value} value={plan.value}>
                                {plan.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.plan} />
            </div>

            <div className="flex items-center gap-3">
                <Button type="submit" disabled={processing}>
                    {processing && <Spinner />}
                    {submitLabel}
                </Button>
                <Button variant="ghost" asChild>
                    <Link href={index()}>{t('ui.common.cancel')}</Link>
                </Button>
            </div>
        </form>
    );
}
