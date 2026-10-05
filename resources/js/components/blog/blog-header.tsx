import { Link } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { home } from '@/routes';
import blog from '@/routes/blog';

export default function BlogHeader() {
    return (
        <header className="bg-brand-navy-deep">
            <div className="mx-auto flex max-w-7xl items-center gap-8 px-6 py-4">
                <Link href={home()} className="flex items-center">
                    <AppLogo variant="light" />
                </Link>
                <nav className="ml-auto flex items-center gap-6">
                    <Link
                        href={home()}
                        className="text-sm font-medium text-white/70 hover:text-white"
                    >
                        Inicio
                    </Link>
                    <Link
                        href={blog.index()}
                        className="text-sm font-medium text-white"
                    >
                        Blog
                    </Link>
                </nav>
            </div>
        </header>
    );
}
