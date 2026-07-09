import { Link } from '@inertiajs/react';
import {
    Building2,
    CalendarClock,
    CalendarDays,
    IdCard,
    LayoutGrid,
    MapPin,
    ShieldCheck,
    Users,
} from 'lucide-react';
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
import { index as companiesIndex } from '@/routes/companies';
import { index as employeesIndex } from '@/routes/employees';
import { index as positionsIndex } from '@/routes/positions';
import { index as premisesIndex } from '@/routes/premises';
import { index as rolesIndex } from '@/routes/roles';
import { index as holidaysIndex } from '@/routes/holidays';
import { index as shiftsIndex } from '@/routes/shifts';
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
                    title: t('ui.nav.companies'),
                    href: companiesIndex(),
                    icon: Building2,
                },
                {
                    title: t('ui.nav.premises'),
                    href: premisesIndex(),
                    icon: MapPin,
                },
                {
                    title: t('ui.nav.positions'),
                    href: positionsIndex(),
                    icon: IdCard,
                },
                {
                    title: t('ui.nav.employees'),
                    href: employeesIndex(),
                    icon: Users,
                },
            ],
        },
        {
            label: t('ui.nav.workdays'),
            items: [
                {
                    title: t('ui.nav.shifts'),
                    href: shiftsIndex(),
                    icon: CalendarClock,
                },
                {
                    title: t('ui.nav.holidays'),
                    href: holidaysIndex(),
                    icon: CalendarDays,
                },
            ],
        },
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
