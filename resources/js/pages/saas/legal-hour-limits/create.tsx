import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import LegalHourLimitForm from '@/components/legal-hour-limit-form';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslations } from '@/hooks/use-translations';
import { store } from '@/routes/saas/legal-hour-limits';

export default function CreateLegalHourLimit() {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.saas_legal_hour_limits.create.title')} />

            <div className="space-y-6">
                <Heading
                    title={t('ui.saas_legal_hour_limits.create.title')}
                    description={t(
                        'ui.saas_legal_hour_limits.create.description',
                    )}
                />

                <Card>
                    <CardContent>
                        <LegalHourLimitForm
                            mode="create"
                            action={store().url}
                            submitLabel={t(
                                'ui.saas_legal_hour_limits.create.submit',
                            )}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
