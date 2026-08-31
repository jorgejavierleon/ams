import type { ReactNode } from 'react';
import type { BreadcrumbItem } from '@/types/navigation';

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

export type AppVariant = 'header' | 'sidebar';

/**
 * A single entry in the paginator's `links` array: one numbered page (or an
 * ellipsis gap, which carries a `null` url).
 */
export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

/**
 * Metadata returned by Laravel's `LengthAwarePaginator` (via
 * `->through()`/`->withQueryString()`), without the `data` payload.
 */
export type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: PaginationLink[];
};

/**
 * A Laravel length-aware paginated response for a collection of `T`.
 */
export type Paginated<T> = PaginationMeta & {
    data: T[];
};

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
};
