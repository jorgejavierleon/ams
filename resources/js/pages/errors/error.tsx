import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Home } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { dashboard } from '@/routes';

type Props = {
    status: 403 | 404 | 419 | 429 | 500 | 503;
};

export default function ErrorPage({ status }: Props) {
    const { t } = useTranslations();
    const title = t(`ui.errors.${status}.title`);

    return (
        <>
            <Head title={title} />
            <div className="flex min-h-[60vh] flex-col items-center justify-center gap-4 px-4 text-center">
                <p className="text-6xl font-semibold text-muted-foreground">
                    {status}
                </p>
                <h1 className="text-2xl font-semibold">{title}</h1>
                <p className="max-w-md text-muted-foreground">
                    {t(`ui.errors.${status}.description`)}
                </p>
                <div className="mt-2 flex flex-wrap justify-center gap-2">
                    <Button
                        variant="outline"
                        onClick={() => window.history.back()}
                    >
                        <ArrowLeft />
                        {t('ui.errors.back')}
                    </Button>
                    <Button asChild>
                        <Link href={dashboard()}>
                            <Home />
                            {t('ui.errors.home')}
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
