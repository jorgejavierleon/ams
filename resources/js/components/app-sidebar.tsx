import { Link } from '@inertiajs/react';
import { IdCard, LayoutGrid, ShieldCheck } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useTranslations } from '@/hooks/use-translations';
import { dashboard } from '@/routes';
import { index as positionsIndex } from '@/routes/positions';
import { index as rolesIndex } from '@/routes/roles';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { t } = useTranslations();

    const navGroups: Array<{ label: string; items: NavItem[] }> = [
        {
            label: t('ui.nav.organization'),
            items: [
                {
                    title: t('ui.nav.dashboard'),
                    href: dashboard(),
                    icon: LayoutGrid,
                },
                {
                    title: t('ui.nav.positions'),
                    href: positionsIndex(),
                    icon: IdCard,
                },
            ],
        },
        { label: t('ui.nav.workdays'), items: [] },
        { label: t('ui.nav.documents'), items: [] },
        {
            label: t('ui.nav.settings'),
            items: [
                {
                    title: t('ui.nav.roles'),
                    href: rolesIndex(),
                    icon: ShieldCheck,
                },
            ],
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {navGroups
                    .filter((group) => group.items.length > 0)
                    .map((group) => (
                        <NavMain
                            key={group.label}
                            label={group.label}
                            items={group.items}
                        />
                    ))}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
