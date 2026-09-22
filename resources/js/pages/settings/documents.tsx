import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { SettingToggle } from '@/components/settings/setting-toggle';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslations } from '@/hooks/use-translations';
import { update } from '@/routes/settings-documents';

type DocumentsForm = {
    documents_signature_enabled: boolean;
    documents_require_ordered_signing: boolean;
};

const fieldKeys = Object.keys({
    documents_signature_enabled: true,
    documents_require_ordered_signing: true,
} satisfies DocumentsForm) as (keyof DocumentsForm)[];

type Props = {
    settings: DocumentsForm;
};

export default function Documents({ settings }: Props) {
    const { t } = useTranslations();
    const { data, setData, patch, processing } = useForm<DocumentsForm>({
        ...settings,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        patch(update().url, { preserveScroll: true });
    }

    return (
        <>
            <Head title={t('ui.settings.documents.head')} />

            <h1 className="sr-only">{t('ui.settings.documents.head')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('ui.settings.documents.title')}
                    description={t('ui.settings.documents.description')}
                />

                <Card>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="divide-y">
                                {fieldKeys.map((key) => (
                                    <SettingToggle
                                        key={key}
                                        id={key}
                                        label={t(
                                            `ui.settings.documents.fields.${key}.label`,
                                        )}
                                        hint={t(
                                            `ui.settings.documents.fields.${key}.hint`,
                                        )}
                                        checked={data[key]}
                                        onCheckedChange={(value) =>
                                            setData(key, value)
                                        }
                                    />
                                ))}
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {t('ui.common.save')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
