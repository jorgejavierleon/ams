import AuthSimpleLayout from '@/layouts/auth/auth-simple-layout';
import type { AuthLayoutProps } from '@/types';

export default function GuestLayout({
    title = '',
    description = '',
    children,
}: AuthLayoutProps) {
    return (
        <AuthSimpleLayout title={title} description={description}>
            {children}
        </AuthSimpleLayout>
    );
}
