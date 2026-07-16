import { Link, useForm } from '@inertiajs/react';
import { FileDown } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Combobox } from '@/components/combobox';
import type { ComboboxOption } from '@/components/combobox';
import { FormField } from '@/components/form-field';
import { RichEditor } from '@/components/rich-editor';
import type { DocumentVariable } from '@/components/rich-editor';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Toggle } from '@/components/ui/toggle';
import { useTranslations } from '@/hooks/use-translations';
import { body as templateBody } from '@/routes/document-templates';
import { index } from '@/routes/documents';

export type DocumentTemplateOption = { id: number; title: string };

export type DocumentTypeOption = ComboboxOption & { signable: boolean };

export type DocumentFormOptions = {
    types: DocumentTypeOption[];
    employees: ComboboxOption[];
    variables: DocumentVariable[];
};

export type DocumentFormData = {
    title: string;
    type: string;
    user_id: string;
    body: string;
    legal_rep_signatories: string;
    ordered_signing: boolean;
};

type Props = {
    method: 'post' | 'patch';
    action: string;
    submitLabel: string;
    options: DocumentFormOptions;
    initial: DocumentFormData;
    templates?: DocumentTemplateOption[];
};

export default function DocumentForm({
    method,
    action,
    submitLabel,
    options,
    initial,
    templates,
}: Props) {
    const { t } = useTranslations();
    const { data, setData, post, patch, processing, errors } =
        useForm<DocumentFormData>(initial);
    const [templatePickerOpen, setTemplatePickerOpen] = useState(false);
    const [loadingTemplate, setLoadingTemplate] = useState(false);

    async function loadTemplate(id: number) {
        setTemplatePickerOpen(false);
        setLoadingTemplate(true);

        try {
            const response = await fetch(templateBody(id).url, {
                headers: { Accept: 'application/json' },
            });
            const payload = (await response.json()) as { body: string };
            setData('body', payload.body ?? '');
        } finally {
            setLoadingTemplate(false);
        }
    }

    const selectedType = options.types.find((type) => type.value === data.type);
    const showSignatureConfig = selectedType?.signable ?? false;
    const showOrderedSigning = data.legal_rep_signatories === '2';

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
        <form onSubmit={submit} noValidate className="grid max-w-3xl gap-6">
            <FormField
                label={t('ui.documents.form.title')}
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

            <div className="grid gap-6 sm:grid-cols-2">
                <FormField
                    label={t('ui.documents.form.type')}
                    htmlFor="type"
                    error={errors.type}
                >
                    <Combobox
                        id="type"
                        options={options.types}
                        value={data.type}
                        onChange={(value) => setData('type', value)}
                        placeholder={t('ui.documents.form.type_placeholder')}
                        searchPlaceholder={t('ui.common.search')}
                        emptyLabel={t('ui.common.no_results')}
                    />
                </FormField>

                <FormField
                    label={t('ui.documents.form.employee')}
                    htmlFor="user_id"
                    required
                    error={errors.user_id}
                >
                    <Combobox
                        id="user_id"
                        options={options.employees}
                        value={data.user_id}
                        onChange={(value) => setData('user_id', value)}
                        placeholder={t(
                            'ui.documents.form.employee_placeholder',
                        )}
                        searchPlaceholder={t('ui.common.search')}
                        emptyLabel={t('ui.common.no_results')}
                    />
                </FormField>
            </div>

            {templates && templates.length > 0 && (
                <div className="flex items-center gap-3">
                    <Popover
                        open={templatePickerOpen}
                        onOpenChange={setTemplatePickerOpen}
                    >
                        <PopoverTrigger asChild>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={loadingTemplate}
                            >
                                {loadingTemplate ? (
                                    <Spinner />
                                ) : (
                                    <FileDown className="size-4" />
                                )}
                                {t('ui.documents.form.load_template')}
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent className="w-80 p-0" align="start">
                            <Command>
                                <CommandInput
                                    placeholder={t(
                                        'ui.documents.form.template_search',
                                    )}
                                />
                                <CommandList>
                                    <CommandEmpty>
                                        {t('ui.documents.form.template_empty')}
                                    </CommandEmpty>
                                    <CommandGroup>
                                        {templates.map((template) => (
                                            <CommandItem
                                                key={template.id}
                                                value={template.title}
                                                onSelect={() =>
                                                    loadTemplate(template.id)
                                                }
                                            >
                                                {template.title}
                                            </CommandItem>
                                        ))}
                                    </CommandGroup>
                                </CommandList>
                            </Command>
                        </PopoverContent>
                    </Popover>
                    <p className="text-xs text-muted-foreground">
                        {t('ui.documents.form.load_template_hint')}
                    </p>
                </div>
            )}

            <FormField
                label={t('ui.documents.form.body')}
                error={errors.body}
                hint={t('ui.documents.form.body_hint')}
            >
                <RichEditor
                    value={data.body}
                    onChange={(html) => setData('body', html)}
                    variables={options.variables}
                    placeholder={t('ui.documents.form.body_placeholder')}
                />
            </FormField>

            {showSignatureConfig && (
                <fieldset className="grid gap-4 rounded-md border border-input p-4">
                    <legend className="px-1 text-sm font-medium">
                        {t('ui.documents.form.signature_config')}
                    </legend>

                    <FormField
                        label={t('ui.documents.form.legal_rep_signatories')}
                        htmlFor="legal_rep_signatories"
                        error={errors.legal_rep_signatories}
                        hint={t('ui.documents.form.legal_rep_signatories_hint')}
                    >
                        <Select
                            value={data.legal_rep_signatories}
                            onValueChange={(value) =>
                                setData('legal_rep_signatories', value)
                            }
                        >
                            <SelectTrigger
                                id="legal_rep_signatories"
                                className="w-[180px]"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="0">0</SelectItem>
                                <SelectItem value="1">1</SelectItem>
                                <SelectItem value="2">2</SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>

                    {showOrderedSigning && (
                        <div className="flex items-center gap-3">
                            <Toggle
                                variant="outline"
                                pressed={data.ordered_signing}
                                onPressedChange={(pressed) =>
                                    setData('ordered_signing', pressed)
                                }
                                aria-labelledby="ordered_signing_label"
                            >
                                {data.ordered_signing
                                    ? t('ui.common.yes')
                                    : t('ui.common.no')}
                            </Toggle>
                            <div className="grid gap-0.5">
                                <Label id="ordered_signing_label">
                                    {t('ui.documents.form.ordered_signing')}
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    {t(
                                        'ui.documents.form.ordered_signing_hint',
                                    )}
                                </p>
                            </div>
                        </div>
                    )}
                </fieldset>
            )}

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
