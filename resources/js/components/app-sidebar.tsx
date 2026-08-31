import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Building2,
    CalendarClock,
    CalendarDays,
    CalendarRange,
    ClipboardList,
    FileText,
    IdCard,
    LayoutGrid,
    LayoutTemplate,
    ListChecks,
    MapPin,
    ShieldCheck,
    SlidersHorizontal,
    Sun,
    Timer,
    Users,
    Wallet,
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
import { edit as companyEdit } from '@/routes/company';
import { index as costCentersIndex } from '@/routes/cost-centers';
import { index as documentTemplatesIndex } from '@/routes/document-templates';
import { index as documentsIndex } from '@/routes/documents';
import { index as employeesIndex } from '@/routes/employees';
import { index as holidaysIndex } from '@/routes/holidays';
import {
    calendar as leavesCalendar,
    index as leavesIndex,
} from '@/routes/leaves';
import { index as myDocumentsIndex } from '@/routes/my/documents';
import { index as myLeavesIndex } from '@/routes/my/leaves';
import { index as myWorkdaysIndex } from '@/routes/my/workdays';
import { edit as organizationSettingsEdit } from '@/routes/organization-settings';
import { index as overtimeIndex } from '@/routes/overtime';
import { index as overtimeRequestsIndex } from '@/routes/overtime/requests';
import {
    overtimeExcess as payrollReportsOvertimeExcess,
    periodMovements as payrollReportsPeriodMovements,
    summary as payrollReportsSummary,
    weeklyDetail as payrollReportsWeeklyDetail,
} from '@/routes/payroll-reports';
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
    const canViewOwnDocuments = auth.permissions.includes('ViewOwn:Document');
    const canViewOwnOvertime = auth.permissions.includes(
        'ViewOwn:OvertimeAuthorization',
    );
    const canManageOvertime =
        auth.permissions.includes('ViewTeam:OvertimeAuthorization') ||
        auth.permissions.includes('ApproveTeam:OvertimeAuthorization') ||
        auth.permissions.includes('Manage:OvertimeAuthorization');
    // KOL-71: a supervisor's own view of Jornadas — separate from
    // canViewOwnWorkdays above, which is their personal "Mis jornadas" link.
    const canViewTeamWorkdays = auth.permissions.includes('ViewTeam:Workday');
    // KOL-18: the payroll reports section, gated on its own permission rather
    // than shown to every admin-nav user.
    const canViewPayrollReports = auth.permissions.includes(
        'View:PayrollReport',
    );

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
                ...(canViewOwnDocuments
                    ? [
                          {
                              title: t('ui.nav.my_documents'),
                              href: myDocumentsIndex(),
                              icon: FileText,
                              badge: auth.pendingSignaturesCount,
                          },
                      ]
                    : []),
                ...(canViewOwnOvertime
                    ? [
                          {
                              title: t('ui.nav.overtime'),
                              href: overtimeIndex(),
                              icon: Timer,
                          },
                      ]
                    : []),
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
                    title: t('ui.nav.company'),
                    href: companyEdit(),
                    icon: Building2,
                },
                {
                    title: t('ui.nav.cost_centers'),
                    href: costCentersIndex(),
                    icon: Wallet,
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
                ...(canViewTeamWorkdays
                    ? [
                          {
                              title: t('ui.nav.workdays_list'),
                              href: workdaysIndex(),
                              icon: ClipboardList,
                          },
                      ]
                    : []),
                ...(canManageOvertime
                    ? [
                          {
                              title: t('ui.nav.overtime'),
                              href: overtimeIndex(),
                              icon: Timer,
                          },
                          {
                              title: t('ui.nav.overtime_requests'),
                              href: overtimeRequestsIndex(),
                              icon: ListChecks,
                              badge: auth.pendingOvertimeRequestsCount,
                          },
                      ]
                    : []),
            ],
        },
        {
            label: t('ui.nav.documents'),
            items: [
                {
                    title: t('ui.nav.documents_list'),
                    href: documentsIndex(),
                    icon: FileText,
                },
                {
                    title: t('ui.nav.document_templates'),
                    href: documentTemplatesIndex(),
                    icon: LayoutTemplate,
                },
            ],
        },
        ...(canViewPayrollReports
            ? [
                  {
                      label: t('ui.nav.reports'),
                      items: [
                          {
                              title: t('ui.nav.payroll_reports'),
                              href: payrollReportsSummary(),
                              icon: BarChart3,
                          },
                          {
                              title: t('ui.payroll_reports.types.weekly-detail'),
                              href: payrollReportsWeeklyDetail(),
                              icon: BarChart3,
                          },
                          {
                              title: t('ui.payroll_reports.types.period-movements'),
                              href: payrollReportsPeriodMovements(),
                              icon: BarChart3,
                          },
                          {
                              title: t('ui.payroll_reports.types.overtime-excess'),
                              href: payrollReportsOvertimeExcess(),
                              icon: BarChart3,
                          },
                      ],
                  },
              ]
            : []),
        {
            label: t('ui.nav.settings'),
            items: [
                {
                    title: t('ui.nav.roles'),
                    href: rolesIndex(),
                    icon: ShieldCheck,
                },
                {
                    title: t('ui.nav.organization_settings'),
                    href: organizationSettingsEdit(),
                    icon: SlidersHorizontal,
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
