import { flexRender } from '@tanstack/react-table';
import type { ColumnDef } from '@tanstack/react-table';
import type { ReactNode } from 'react';
import { DataTablePagination } from '@/components/data-table-pagination';
import { DataTableViewOptions } from '@/components/data-table-view-options';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    useServerTable
    
} from '@/hooks/use-server-table';
import type {ServerTableFilters} from '@/hooks/use-server-table';
import { useTranslations } from '@/hooks/use-translations';
import type { Paginated } from '@/types/ui';

type DataTableProps<TData> = {
    /**
     * The table rows. A Laravel paginated payload, or a plain `{ data: [...] }`
     * list when the page renders every row. A new identity each Inertia visit.
     */
    data: Paginated<TData> | { data: TData[] };
    columns: ColumnDef<TData, unknown>[];
    /** Base URL to reload (typically `route().url`). */
    routeUrl: string;
    /** Server-side filters echoed back from the controller. */
    filters?: ServerTableFilters;
    /** Extra filter params merged into every reload (e.g. faceted filters). */
    extraParams?: Record<string, string | string[] | undefined>;
    /** Inertia partial-reload keys, e.g. `['positions', 'filters']`. */
    only?: string[];
    /** When set, renders a debounced search box with this placeholder. */
    searchPlaceholder?: string;
    /** Message shown when there are no rows. Defaults to a shared string. */
    emptyLabel?: string;
    /** Render the pagination footer. Defaults to `true`. */
    showPagination?: boolean;
    enableRowSelection?: boolean;
    getRowId?: (row: TData, index: number) => string;
    /** Extra controls rendered on the left of the toolbar row. */
    toolbar?: ReactNode;
};

/**
 * Server-driven data table built on TanStack Table + shadcn primitives.
 * Search, sorting, and pagination are handled by the backend via Inertia
 * partial reloads; the page only supplies `data`, `columns`, and the route.
 *
 * `useReactTable` lives here (not in the page) so React Compiler re-renders
 * this component whenever the `data` prop changes identity on each visit.
 */
export function DataTable<TData>({
    data,
    columns,
    routeUrl,
    filters,
    extraParams,
    only,
    searchPlaceholder,
    emptyLabel,
    showPagination = true,
    enableRowSelection,
    getRowId,
    toolbar,
}: DataTableProps<TData>) {
    // TanStack Table's instance has a stable identity the compiler cannot
    // track; opt this component out so state reads stay live.
    'use no memo';

    const { t } = useTranslations();
    const { table, search, setSearch } = useServerTable({
        data,
        columns,
        routeUrl,
        filters,
        extraParams,
        only,
        enableRowSelection,
        getRowId,
    });

    const columnCount = table.getAllLeafColumns().length;
    const hasToolbar = Boolean(searchPlaceholder) || Boolean(toolbar);

    return (
        <div className="space-y-4">
            {hasToolbar && (
                <div className="flex items-center justify-between gap-4">
                    {searchPlaceholder ? (
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={searchPlaceholder}
                            className="max-w-sm"
                        />
                    ) : (
                        <div />
                    )}
                    <div className="flex items-center gap-2">
                        {toolbar}
                        <DataTableViewOptions table={table} />
                    </div>
                </div>
            )}

            <div className="rounded-lg border">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => (
                                    <TableHead
                                        key={header.id}
                                        className={
                                            (
                                                header.column.columnDef.meta as {
                                                    headClassName?: string;
                                                }
                                            )?.headClassName
                                        }
                                    >
                                        {header.isPlaceholder
                                            ? null
                                            : flexRender(
                                                  header.column.columnDef.header,
                                                  header.getContext(),
                                              )}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows.length > 0 ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow
                                    key={row.id}
                                    data-state={
                                        row.getIsSelected()
                                            ? 'selected'
                                            : undefined
                                    }
                                >
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell
                                            key={cell.id}
                                            className={
                                                (
                                                    cell.column.columnDef
                                                        .meta as {
                                                        cellClassName?: string;
                                                    }
                                                )?.cellClassName
                                            }
                                        >
                                            {flexRender(
                                                cell.column.columnDef.cell,
                                                cell.getContext(),
                                            )}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell
                                    colSpan={columnCount}
                                    className="py-8 text-center text-muted-foreground"
                                >
                                    {emptyLabel ?? t('ui.common.data_table.empty')}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            {showPagination && 'total' in data && (
                <DataTablePagination meta={data} />
            )}
        </div>
    );
}
