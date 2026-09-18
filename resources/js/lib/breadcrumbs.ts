import type { Page } from '@inertiajs/core';
import type { InertiaLinkProps } from '@inertiajs/react';
import { dashboard } from '@/routes';
import { edit as appearanceEdit } from '@/routes/appearance';
import { index as documentTemplatesIndex } from '@/routes/document-templates';
import { index as documentsIndex } from '@/routes/documents';
import { index as employeesIndex } from '@/routes/employees';
import { edit as organizationSettingsEdit } from '@/routes/organization-settings';
import { index as overtimeIndex } from '@/routes/overtime';
import { summary as payrollReportsSummary } from '@/routes/payroll-reports';
import { edit as profileEdit } from '@/routes/profile';
import { index as rolesIndex } from '@/routes/roles';
import { edit as securityEdit } from '@/routes/security';

/**
 * Builds a title resolver that reads a display field off a named prop on the
 * current page, e.g. `propTitle('employee', 'name')` reads `props.employee.name`.
 */
function propTitle(prop: string, field: string): BreadcrumbTitle {
    return (props: Page['props']) => {
        const record = props[prop] as Record<string, unknown> | undefined;
        const value = record?.[field];

        return typeof value === 'string' ? value : '';
    };
}

export type BreadcrumbTitle = string | ((props: Page['props']) => string);

export type BreadcrumbRegistryEntry = {
    /**
     * A translation key resolved via `t()`, or a function that derives the
     * title from the current page's own props (e.g. a record's name).
     */
    title: BreadcrumbTitle;
    href: NonNullable<InertiaLinkProps['href']>;
    /** Registry key of the parent crumb. May point at a virtual entry. */
    parent?: string;
};

/**
 * Maps each Inertia page name — the same string passed to
 * `Inertia::render()`/`Route::inertia()`, already unique per page — to its
 * breadcrumb trail entry. `useBreadcrumbs` resolves the current page's entry
 * and walks the `parent` chain to the root.
 *
 * Not every key here is a routed page: `settings` is a virtual entry shared
 * by the three account Settings pages so their trail reads "Settings > <page>"
 * even though Settings isn't a top-level sidebar nav item.
 */
export const breadcrumbRegistry: Record<string, BreadcrumbRegistryEntry> = {
    dashboard: {
        title: 'ui.nav.dashboard',
        href: dashboard(),
    },
    'organization-settings': {
        title: 'ui.nav.organization_settings',
        href: organizationSettingsEdit(),
    },
    'roles/index': {
        title: 'ui.nav.roles',
        href: rolesIndex(),
    },
    'roles/show': {
        title: 'ui.roles.columns.permissions',
        href: '#',
        parent: 'roles/index',
    },
    'employees/index': {
        title: 'ui.nav.employees',
        href: employeesIndex(),
    },
    'employees/create': {
        title: 'ui.employees.new',
        href: '#',
        parent: 'employees/index',
    },
    'employees/show': {
        title: propTitle('employee', 'name'),
        href: '#',
        parent: 'employees/index',
    },
    'employees/edit': {
        title: propTitle('employee', 'name'),
        href: '#',
        parent: 'employees/index',
    },
    'imports/employees/create': {
        title: 'ui.employees.import.nav',
        href: '#',
        parent: 'employees/index',
    },
    'imports/employees/show': {
        title: 'ui.employees.import.nav',
        href: '#',
        parent: 'employees/index',
    },
    'documents/index': {
        title: 'ui.nav.documents_list',
        href: documentsIndex(),
    },
    'documents/create': {
        title: 'ui.documents.new',
        href: '#',
        parent: 'documents/index',
    },
    'documents/show': {
        title: propTitle('document', 'title'),
        href: '#',
        parent: 'documents/index',
    },
    'documents/edit': {
        title: propTitle('document', 'title'),
        href: '#',
        parent: 'documents/index',
    },
    'document-templates/index': {
        title: 'ui.nav.document_templates',
        href: documentTemplatesIndex(),
    },
    'document-templates/create': {
        title: 'ui.document_templates.new',
        href: '#',
        parent: 'document-templates/index',
    },
    'document-templates/edit': {
        title: propTitle('template', 'title'),
        href: '#',
        parent: 'document-templates/index',
    },
    'overtime/index': {
        title: 'ui.nav.overtime',
        href: overtimeIndex(),
    },
    'overtime/pacts/index': {
        title: 'ui.overtime.pacts.title',
        href: '#',
        parent: 'overtime/index',
    },
    'overtime/requests/index': {
        title: 'ui.overtime.requests.review.title',
        href: '#',
        parent: 'overtime/index',
    },
    'overtime/rest-day-balances/index': {
        title: 'ui.overtime.rest_day_balances.title',
        href: '#',
        parent: 'overtime/index',
    },
    'payroll-reports/summary': {
        title: 'ui.nav.payroll_reports',
        href: payrollReportsSummary(),
    },
    'payroll-reports/weekly-detail': {
        title: 'ui.payroll_reports.types.weekly-detail',
        href: '#',
        parent: 'payroll-reports/summary',
    },
    'payroll-reports/period-movements': {
        title: 'ui.payroll_reports.types.period-movements',
        href: '#',
        parent: 'payroll-reports/summary',
    },
    'payroll-reports/overtime-excess': {
        title: 'ui.payroll_reports.types.overtime-excess',
        href: '#',
        parent: 'payroll-reports/summary',
    },
    'payroll-reports/history': {
        title: 'ui.payroll_reports.history.title',
        href: '#',
        parent: 'payroll-reports/summary',
    },
    // Virtual: no page renders as "settings" — this only groups the three
    // pages below under a shared parent crumb.
    settings: {
        title: 'ui.nav.settings',
        href: profileEdit(),
    },
    'settings/profile': {
        title: 'ui.settings.nav.profile',
        href: profileEdit(),
        parent: 'settings',
    },
    'settings/security': {
        title: 'ui.settings.nav.security',
        href: securityEdit(),
        parent: 'settings',
    },
    'settings/appearance': {
        title: 'ui.settings.nav.appearance',
        href: appearanceEdit(),
        parent: 'settings',
    },
};
