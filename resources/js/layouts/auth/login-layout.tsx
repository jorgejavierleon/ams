import { Link } from '@inertiajs/react';
import { useTranslations } from '@/hooks/use-translations';
import AuthCardLayout from '@/layouts/auth/auth-card-layout';
import { login as dtLogin } from '@/routes/dt';
import type { AuthLayoutProps } from '@/types';

export default function LoginLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { t } = useTranslations();

    return (
        <AuthCardLayout
            title={title}
            description={description}
            footer={
                <Link
                    href={dtLogin()}
                    className="inline-block self-center py-2 text-center text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                >
                    {t('ui.auth.dt_login_link')}
                </Link>
            }
        >
            {children}
        </AuthCardLayout>
    );
}
