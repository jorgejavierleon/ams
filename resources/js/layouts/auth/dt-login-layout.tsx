import AuthCardLayout from '@/layouts/auth/auth-card-layout';
import type { AuthLayoutProps } from '@/types';

export default function DtLoginLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <AuthCardLayout title={title} description={description}>
            {children}
        </AuthCardLayout>
    );
}
