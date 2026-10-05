import { Link } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { home } from '@/routes';

export default function SiteFooter() {
    return (
        <footer className="border-t py-8">
            <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-6 sm:flex-row">
                <Link href={home()} className="flex items-center">
                    <AppLogo />
                </Link>
                <p className="text-sm text-muted-foreground">
                    © 2026 Kolvi · Santiago, Chile
                </p>
            </div>
        </footer>
    );
}
