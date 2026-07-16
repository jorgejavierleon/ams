import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    Fingerprint,
    LayoutGrid,
    LogOut,
    TriangleAlert,
} from 'lucide-react';
import MarkValidationController from '@/actions/App/Http/Controllers/Dt/MarkValidationController';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useInitials } from '@/hooks/use-initials';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { dashboard, logout } from '@/routes/dt';
import { index as incidents } from '@/routes/dt/incidents';
import { select as selectOrganization } from '@/routes/dt/organization';
import type { AppLayoutProps, NavItem } from '@/types';

export default function DtLayout({ children }: AppLayoutProps) {
    const { auth, dtOrganization } = usePage().props;
    const getInitials = useInitials();
    const { t } = useTranslations();
    const { isCurrentUrl } = useCurrentUrl();

    const navItems: NavItem[] = [
        {
            title: t('ui.dt.nav.dashboard'),
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: t('ui.dt.nav.validate_mark'),
            href: MarkValidationController.create(),
            icon: Fingerprint,
        },
        {
            title: t('ui.dt.nav.incidents'),
            href: incidents(),
            icon: TriangleAlert,
        },
        {
            title: t('ui.dt.nav.select_organization'),
            href: selectOrganization(),
            icon: Building2,
        },
    ];

    return (
        <AppShell variant="header">
            <div className="border-b border-sidebar-border/80">
                <div className="mx-auto flex h-16 items-center gap-6 px-4 md:max-w-7xl">
                    <span className="text-sm font-semibold whitespace-nowrap">
                        AMS – DT
                    </span>

                    {auth.user && dtOrganization?.name && (
                        <span className="hidden items-center gap-1.5 rounded-md bg-accent px-2.5 py-1 text-xs font-medium text-accent-foreground sm:inline-flex">
                            <Building2 className="size-3.5" />
                            {dtOrganization.name}
                        </span>
                    )}

                    {auth.user && (
                        <nav className="flex items-center gap-1">
                            {navItems.map((item) => {
                                const active = isCurrentUrl(item.href);

                                return (
                                    // No prefetch: the org-scoped DT views are
                                    // gated by EnsureDtOrganizationSelected, so
                                    // prefetching before (or across) an audit
                                    // selection caches a redirect to the selector
                                    // — or another employer's page — and serves
                                    // it on the next click.
                                    <Link
                                        key={item.title}
                                        href={item.href}
                                        className={cn(
                                            'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                                            active
                                                ? 'bg-accent text-accent-foreground'
                                                : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                                        )}
                                    >
                                        {item.icon && (
                                            <item.icon className="size-4" />
                                        )}
                                        <span>{item.title}</span>
                                    </Link>
                                );
                            })}
                        </nav>
                    )}

                    {auth.user && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    className="ml-auto size-10 rounded-full p-1"
                                >
                                    <Avatar className="size-8 overflow-hidden rounded-full">
                                        <AvatarImage
                                            src={auth.user.avatar}
                                            alt={auth.user.name}
                                        />
                                        <AvatarFallback className="rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                            {getInitials(auth.user.name ?? '')}
                                        </AvatarFallback>
                                    </Avatar>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent className="w-56" align="end">
                                <DropdownMenuLabel className="p-0 font-normal">
                                    <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                        <UserInfo
                                            user={auth.user}
                                            showEmail={true}
                                        />
                                    </div>
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild>
                                    <Link
                                        className="block w-full cursor-pointer"
                                        href={logout()}
                                        as="button"
                                        data-test="logout-button"
                                    >
                                        <LogOut className="mr-2" />
                                        Cerrar sesión
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            </div>
            <AppContent variant="header">{children}</AppContent>
        </AppShell>
    );
}
