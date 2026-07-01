import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import type { AppLayoutProps } from '@/types';

export default function SaasLayout({ children }: AppLayoutProps) {
    const { auth } = usePage().props;
    const getInitials = useInitials();

    return (
        <AppShell variant="header">
            <div className="border-b border-sidebar-border/80">
                <div className="mx-auto flex h-16 items-center justify-between px-4 md:max-w-7xl">
                    <Link
                        href="/saas/dashboard"
                        className="flex items-center gap-2"
                    >
                        <AppLogoIcon className="size-6 fill-current text-[var(--foreground)] dark:text-white" />
                        <span className="text-sm font-semibold">AMS SaaS</span>
                    </Link>
                    {auth.user && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    className="size-10 rounded-full p-1"
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
                                <UserMenuContent user={auth.user} />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            </div>
            <AppContent variant="header">{children}</AppContent>
        </AppShell>
    );
}
