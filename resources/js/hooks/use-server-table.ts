import { router } from '@inertiajs/react';
import {
    getCoreRowModel,
    useReactTable
    
    
    
    
    
} from '@tanstack/react-table';
import type {ColumnDef, RowSelectionState, SortingState, Table, VisibilityState} from '@tanstack/react-table';
import { useEffect, useRef, useState } from 'react';
import type { Paginated } from '@/types/ui';

export type ServerTableFilters = {
    search?: string | null;
    sort?: string | null;
    direction?: 'asc' | 'desc' | null;
};

type UseServerTableOptions<T> = {
    /** The Laravel paginated payload for the current page. */
    data: Paginated<T>;
    /** TanStack column definitions. */
    columns: ColumnDef<T, unknown>[];
    /** Base URL to reload (typically `route().url`). */
    routeUrl: string;
    /** Current server-side filters, echoed back from the controller. */
    filters?: ServerTableFilters;
    /** Inertia partial-reload keys, e.g. `['positions', 'filters']`. */
    only?: string[];
    /** Enable the row-selection checkbox column behaviour. */
    enableRowSelection?: boolean;
    /** Debounce applied to the search input, in ms. */
    searchDebounce?: number;
    /** Stable identity for each row, used for selection state. */
    getRowId?: (row: T, index: number) => string;
};

export type UseServerTableReturn<T> = {
    table: Table<T>;
    search: string;
    setSearch: (value: string) => void;
};

/**
 * Wires a TanStack table to a server-driven Inertia list. Search and sorting
 * are handled by the backend: state changes trigger a partial `router.get`
 * that reloads only the table's props while preserving scroll and history.
 */
export function useServerTable<T>({
    data,
    columns,
    routeUrl,
    filters,
    only,
    enableRowSelection = false,
    searchDebounce = 300,
    getRowId,
}: UseServerTableOptions<T>): UseServerTableReturn<T> {
    // React Compiler must not memoize the TanStack table instance this hook
    // builds; see DataTable for the full rationale.
    'use no memo';

    const [search, setSearch] = useState(filters?.search ?? '');
    const [debouncedSearch, setDebouncedSearch] = useState(search);
    const [sorting, setSorting] = useState<SortingState>(() =>
        filters?.sort
            ? [{ id: filters.sort, desc: filters.direction === 'desc' }]
            : [],
    );
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
    const [columnVisibility, setColumnVisibility] = useState<VisibilityState>(
        {},
    );

    const isFirstRender = useRef(true);

    useEffect(() => {
        const timeout = setTimeout(
            () => setDebouncedSearch(search),
            searchDebounce,
        );

        return () => clearTimeout(timeout);
    }, [search, searchDebounce]);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const sort = sorting[0];

        router.get(
            routeUrl,
            {
                search: debouncedSearch || undefined,
                sort: sort?.id,
                direction: sort ? (sort.desc ? 'desc' : 'asc') : undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only,
            },
        );
        // `only` is a fresh array each render; exclude it to avoid loops.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [debouncedSearch, sorting, routeUrl]);

    const table = useReactTable<T>({
        data: data.data,
        columns,
        state: { sorting, rowSelection, columnVisibility },
        getRowId,
        enableRowSelection,
        enableMultiSort: false,
        manualPagination: true,
        manualSorting: true,
        manualFiltering: true,
        onSortingChange: setSorting,
        onRowSelectionChange: setRowSelection,
        onColumnVisibilityChange: setColumnVisibility,
        getCoreRowModel: getCoreRowModel(),
    });

    return { table, search, setSearch };
}

export type { ColumnDef };
