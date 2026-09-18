import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslations } from '@/hooks/use-translations';
import { breadcrumbRegistry } from '@/lib/breadcrumbs';
import type { BreadcrumbRegistryEntry } from '@/lib/breadcrumbs';
import type { BreadcrumbItem } from '@/types';

/**
 * Resolves the current page's breadcrumb trail from the central registry,
 * walking the `parent` chain to the root. A page without a registry entry
 * resolves to an empty trail, so the header shows nothing rather than a
 * partial trail.
 *
 * Reads only the current page's own name and props — both already present
 * on a fresh full-page load — so the trail is correct on first render, not
 * just after client-side navigation.
 */
export function useBreadcrumbs(): BreadcrumbItem[] {
    const { component, props } = usePage();
    const { t } = useTranslations();

    return useMemo(() => {
        const trail: BreadcrumbItem[] = [];
        // Guards a misconfigured registry (two entries pointing at each
        // other as `parent`) from hanging the render loop.
        const visited = new Set<string>();
        let key: string | undefined = component;

        while (key && !visited.has(key)) {
            visited.add(key);

            const entry: BreadcrumbRegistryEntry | undefined =
                breadcrumbRegistry[key];

            if (!entry) {
                if (import.meta.env.DEV && trail.length > 0) {
                    // Only the current page is allowed to be unregistered
                    // (it simply hasn't been migrated yet). A `parent` key
                    // that doesn't resolve is always a registry typo.
                    console.warn(
                        `useBreadcrumbs: registry entry "${key}" referenced as a parent does not exist.`,
                    );
                }

                break;
            }

            trail.unshift({
                title:
                    typeof entry.title === 'function'
                        ? entry.title(props)
                        : t(entry.title),
                href: entry.href,
            });

            key = entry.parent;
        }

        return trail;
    }, [component, props, t]);
}
