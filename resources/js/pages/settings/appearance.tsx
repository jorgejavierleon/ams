import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslations } from '@/hooks/use-translations';

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
                <Card>
                    <CardContent>
                        <AppearanceTabs />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
