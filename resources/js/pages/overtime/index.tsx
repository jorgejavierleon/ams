import { Head, Link } from '@inertiajs/react';
import { FileText, Timer } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { index as pactsIndex } from '@/routes/overtime/pacts';

type Props = {
    can: {
        managePacts: boolean;
    };
};

export default function OvertimeIndex({ can }: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.overtime.index.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={t('ui.overtime.index.title')}
                        description={t('ui.overtime.index.description')}
                    />
                    {can.managePacts && (
                        <Button variant="outline" asChild>
                            <Link href={pactsIndex()}>
                                <FileText className="size-4" />
                                {t('ui.overtime.pacts.title')}
                            </Link>
                        </Button>
                    )}
                </div>

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
