import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLogoMark from '@/components/app-logo-mark';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

type AuthCardLayoutProps = AuthLayoutProps & {
    footer?: ReactNode;
};

export default function AuthCardLayout({
    children,
    title,
    description,
    footer,
}: AuthCardLayoutProps) {
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

                {footer}
            </div>
        </div>
    );
}
