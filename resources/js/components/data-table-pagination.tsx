import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import type { PaginationMeta } from '@/types/ui';

type DataTablePaginationProps = {
    meta: PaginationMeta;
    /** Optional slot rendered on the left, e.g. a selection summary. */
    children?: React.ReactNode;
};

/**
 * Server-side pagination footer for a Laravel paginated response. Uses
 * Inertia `<Link>`s so navigation is a partial visit preserving scroll/state.
 */
export function DataTablePagination({ meta, children }: DataTablePaginationProps) {
    const { t } = useTranslations();

    return (
        <div className="flex items-center justify-between gap-4">
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
            <div className="flex gap-2">
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
    );
}
