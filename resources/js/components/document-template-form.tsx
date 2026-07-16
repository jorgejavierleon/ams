import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Combobox } from '@/components/combobox';
import type { ComboboxOption } from '@/components/combobox';
import { FormField } from '@/components/form-field';
import { RichEditor } from '@/components/rich-editor';
import type { DocumentVariable } from '@/components/rich-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { index } from '@/routes/document-templates';

export type DocumentTemplateFormOptions = {
    types: ComboboxOption[];
    variables: DocumentVariable[];
};

export type DocumentTemplateFormData = {
    title: string;
    type: string;
    body: string;
};

type Props = {
    method: 'post' | 'patch';
    action: string;
    submitLabel: string;
    options: DocumentTemplateFormOptions;
    initial: DocumentTemplateFormData;
};

export default function DocumentTemplateForm({
    method,
    action,
    submitLabel,
    options,
    initial,
}: Props) {
    const { t } = useTranslations();
    const { data, setData, post, patch, processing, errors } =
        useForm<DocumentTemplateFormData>(initial);

    function submit(event: FormEvent) {
        event.preventDefault();
        const submitOptions = { preserveScroll: true };

        if (method === 'patch') {
            patch(action, submitOptions);
        } else {
            post(action, submitOptions);
        }
    }

    return (
        <form onSubmit={submit} noValidate className="grid gap-6">
            <div className="grid max-w-3xl gap-6 sm:grid-cols-2">
                <FormField
                    label={t('ui.document_templates.form.title')}
                    htmlFor="title"
                    required
                    error={errors.title}
                >
                    <Input
                        id="title"
                        value={data.title}
                        onChange={(e) => setData('title', e.target.value)}
                        autoFocus
                    />
                </FormField>

                <FormField
                    label={t('ui.document_templates.form.type')}
                    htmlFor="type"
                    error={errors.type}
                >
                    <Combobox
                        id="type"
                        options={options.types}
                        value={data.type}
                        onChange={(value) => setData('type', value)}
                        placeholder={t(
                            'ui.document_templates.form.type_placeholder',
                        )}
                        searchPlaceholder={t('ui.common.search')}
                        emptyLabel={t('ui.common.no_results')}
                    />
                </FormField>
            </div>

            <FormField
                label={t('ui.document_templates.form.body')}
                error={errors.body}
                hint={t('ui.document_templates.form.body_hint')}
            >
                <RichEditor
                    value={data.body}
                    onChange={(html) => setData('body', html)}
                    variables={options.variables}
                    placeholder={t(
                        'ui.document_templates.form.body_placeholder',
                    )}
                />
            </FormField>

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
