import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    CalendarClock,
    CalendarDays,
    CalendarRange,
    ClipboardList,
    IdCard,
    LayoutGrid,
    MapPin,
    ShieldCheck,
    Sun,
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
import { index as holidaysIndex } from '@/routes/holidays';
import { calendar as leavesCalendar, index as leavesIndex } from '@/routes/leaves';
import { index as myLeavesIndex } from '@/routes/my/leaves';
import { index as myWorkdaysIndex } from '@/routes/my/workdays';
import { index as positionsIndex } from '@/routes/positions';
import { index as premisesIndex } from '@/routes/premises';
import { index as rolesIndex } from '@/routes/roles';
import { index as shiftsIndex } from '@/routes/shifts';
import { index as workdaysIndex } from '@/routes/workdays';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { t } = useTranslations();
    const { auth } = usePage().props;

    // Feature access is gated by permissions, not roles. Employees see a
    // minimal self-service nav; everyone else keeps the admin navigation.
    const isEmployee = auth.permissions.includes('ViewOwn:Leave');
    // Supervisors are employees who may also review their team's leaves.
    const canReviewTeamLeaves = auth.permissions.includes('ViewTeam:Leave');
    const canViewOwnWorkdays = auth.permissions.includes('ViewOwn:Workday');

    const employeeNavGroups: Array<{ label: string; items: NavItem[] }> = [
        {
            label: t('ui.nav.organization'),
            items: [
                {
                    title: t('ui.nav.dashboard'),
                    href: dashboard(),
                    icon: LayoutGrid,
                },
                ...(canViewOwnWorkdays
                    ? [
                          {
                              title: t('ui.nav.my_workdays'),
                              href: myWorkdaysIndex(),
                              icon: ClipboardList,
                              badge: auth.pendingModificationsCount,
                          },
                      ]
                    : []),
                {
                    title: t('ui.nav.my_leaves'),
                    href: myLeavesIndex(),
                    icon: Sun,
                },
                ...(canReviewTeamLeaves
                    ? [
                          {
                              title: t('ui.nav.team_leaves'),
                              href: leavesIndex(),
                              icon: Users,
                          },
                          {
                              title: t('ui.nav.leaves_calendar'),
                              href: leavesCalendar(),
                              icon: CalendarRange,
                          },
                      ]
                    : []),
            ],
        },
    ];

    const adminNavGroups: Array<{ label: string; items: NavItem[] }> = [
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
                    title: t('ui.nav.workdays_list'),
                    href: workdaysIndex(),
                    icon: ClipboardList,
                },
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
        {
            label: t('ui.nav.approvals'),
            items: [
                {
                    title: t('ui.nav.leaves'),
                    href: leavesIndex(),
                    icon: Sun,
                },
                {
                    title: t('ui.nav.leaves_calendar'),
                    href: leavesCalendar(),
                    icon: CalendarRange,
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

    const navGroups = isEmployee ? employeeNavGroups : adminNavGroups;

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
