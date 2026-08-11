import { Head } from '@inertiajs/react';
import { Timer } from 'lucide-react';
import Heading from '@/components/heading';
import { useTranslations } from '@/hooks/use-translations';

export default function OvertimeIndex() {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.overtime.index.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.overtime.index.title')}
                    description={t('ui.overtime.index.description')}
                />

                <div className="flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed p-12 text-center text-muted-foreground">
                    <Timer className="size-8" />
                    <p className="text-sm">
                        {t('ui.overtime.index.coming_soon')}
                    </p>
                </div>
            </div>
        </>
    );
}
