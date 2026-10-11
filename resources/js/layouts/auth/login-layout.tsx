import { Link } from '@inertiajs/react';
import AppLogoMark from '@/components/app-logo-mark';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTranslations } from '@/hooks/use-translations';
import { home } from '@/routes';
import { login as dtLogin } from '@/routes/dt';
import type { AuthLayoutProps } from '@/types';

export default function LoginLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { t } = useTranslations();

    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10">
            <div className="flex w-full max-w-sm flex-col gap-6">
                <Link
                    href={home()}
                    className="flex items-center justify-center self-center"
                >
                    <AppLogoMark className="h-16 w-16 shrink-0" />
                    <span className="sr-only">Kolvi</span>
                </Link>

                <Card className="rounded-xl">
                    <CardHeader className="text-center">
                        <CardTitle className="text-xl">{title}</CardTitle>
                        <CardDescription>{description}</CardDescription>
                    </CardHeader>
                    <CardContent>{children}</CardContent>
                </Card>

                <Link
                    href={dtLogin()}
                    className="inline-block self-center py-2 text-center text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                >
                    {t('ui.auth.dt_login_link')}
                </Link>
            </div>
        </div>
    );
}
