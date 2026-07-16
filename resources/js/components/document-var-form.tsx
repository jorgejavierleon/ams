import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { index } from '@/routes/saas/document-variables';

type DocumentVarFormData = {
    name: string;
    key: string;
    description: string;
};

type Props = {
    method: 'post' | 'patch';
    action: string;
    submitLabel: string;
    initial?: DocumentVarFormData;
};

export default function DocumentVarForm({
    method,
    action,
    submitLabel,
    initial,
}: Props) {
    const { t } = useTranslations();
    const { data, setData, post, patch, processing, errors } =
        useForm<DocumentVarFormData>({
            name: initial?.name ?? '',
            key: initial?.key ?? '',
            description: initial?.description ?? '',
        });

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
                <Label htmlFor="name">
                    {t('ui.document_variables.form.name')}
                </Label>
                <Input
                    id="name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                    autoFocus
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="key">
                    {t('ui.document_variables.form.key')}
                </Label>
                <Input
                    id="key"
                    value={data.key}
                    onChange={(e) => setData('key', e.target.value)}
                    placeholder="{{employee_name}}"
                    required
                />
                <p className="text-xs text-muted-foreground">
                    {t('ui.document_variables.form.key_hint')}
                </p>
                <InputError message={errors.key} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">
                    {t('ui.document_variables.form.description')}
                </Label>
                <textarea
                    id="description"
                    rows={3}
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                    className="flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                />
                <InputError message={errors.description} />
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
