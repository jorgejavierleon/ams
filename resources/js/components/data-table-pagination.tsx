import { Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/hooks/use-translations';
import type { PaginationMeta } from '@/types/ui';

const ROWS_PER_PAGE_OPTIONS = [10, 25, 50, 100];

type DataTablePaginationProps = {
    meta: PaginationMeta;
    /** Inertia partial-reload keys, mirrored from the parent `DataTable`. */
    only?: string[];
    /** Optional slot rendered on the left, e.g. a selection summary. */
    children?: React.ReactNode;
};

/**
 * Server-side pagination footer for a Laravel paginated response. Uses
 * Inertia `<Link>`s so navigation is a partial visit preserving scroll/state.
 */
export function DataTablePagination({
    meta,
    only,
    children,
}: DataTablePaginationProps) {
    const { t } = useTranslations();

    // Laravel's `links` array wraps the numbered pages with "Previous"/"Next"
    // entries; those are rendered separately below via prev/next_page_url.
    const numberedLinks = meta.links.slice(1, -1);

    function handlePerPageChange(value: string) {
        const params = new URLSearchParams(window.location.search);
        params.set('per_page', value);
        params.delete('page');

        router.get(window.location.pathname, Object.fromEntries(params), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only,
        });
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-4">
            <p className="text-sm text-muted-foreground">
                {children ??
                    (meta.total > 0
                        ? t('ui.common.data_table.pagination.showing', {
                              from: meta.from ?? 0,
                              to: meta.to ?? 0,
                              total: meta.total,
                          })
                        : t('ui.common.data_table.pagination.none'))}
            </p>
            <div className="flex flex-wrap items-center gap-4">
                <div className="flex items-center gap-2">
                    <span className="text-sm text-muted-foreground">
                        {t('ui.common.data_table.pagination.rows_per_page')}
                    </span>
                    <Select
                        value={String(meta.per_page)}
                        onValueChange={handlePerPageChange}
                    >
                        <SelectTrigger className="w-[80px]">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {ROWS_PER_PAGE_OPTIONS.map((option) => (
                                <SelectItem key={option} value={String(option)}>
                                    {option}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!meta.prev_page_url}
                        asChild={Boolean(meta.prev_page_url)}
                    >
                        {meta.prev_page_url ? (
                            <Link
                                href={meta.prev_page_url}
                                preserveScroll
                                preserveState
                            >
                                {t('ui.common.data_table.pagination.previous')}
                            </Link>
                        ) : (
                            <span>
                                {t('ui.common.data_table.pagination.previous')}
                            </span>
                        )}
                    </Button>

                    {numberedLinks.map((link, index) =>
                        link.url === null ? (
                            <span
                                key={index}
                                className="px-2 text-sm text-muted-foreground"
                            >
                                {link.label}
                            </span>
                        ) : (
                            <Button
                                key={index}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                className="min-w-9"
                                asChild
                            >
                                <Link
                                    href={link.url}
                                    preserveScroll
                                    preserveState
                                >
                                    {link.label}
                                </Link>
                            </Button>
                        ),
                    )}

                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!meta.next_page_url}
                        asChild={Boolean(meta.next_page_url)}
                    >
                        {meta.next_page_url ? (
                            <Link
                                href={meta.next_page_url}
                                preserveScroll
                                preserveState
                            >
                                {t('ui.common.data_table.pagination.next')}
                            </Link>
                        ) : (
                            <span>{t('ui.common.data_table.pagination.next')}</span>
                        )}
                    </Button>
                </div>
            </div>
        </div>
    );
}
