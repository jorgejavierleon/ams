import type { Page } from '@inertiajs/core';
import type { InertiaLinkProps } from '@inertiajs/react';
import { dashboard } from '@/routes';
import { edit as appearanceEdit } from '@/routes/appearance';
import { edit as organizationSettingsEdit } from '@/routes/organization-settings';
import { edit as profileEdit } from '@/routes/profile';
import { index as rolesIndex } from '@/routes/roles';
import { edit as securityEdit } from '@/routes/security';

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
