import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { useTranslations } from '@/hooks/use-translations';
import { translate } from '@/lib/i18n';
import { edit as editAppearance } from '@/routes/appearance';
import type { Translations } from '@/types/i18n';

export default function Appearance() {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.settings.appearance.head')} />

            <h1 className="sr-only">{t('ui.settings.appearance.head')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('ui.settings.appearance.title')}
                    description={t('ui.settings.appearance.description')}
                />
                <AppearanceTabs />
            </div>
        </>
    );
}

Appearance.layout = (props: { translations: Translations }) => ({
    breadcrumbs: [
        {
            title: translate(props.translations, 'ui.settings.appearance.head'),
            href: editAppearance(),
        },
    ],
});
