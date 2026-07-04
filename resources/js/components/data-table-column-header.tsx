import type { Column } from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';
import { cn } from '@/lib/utils';

type DataTableColumnHeaderProps<TData, TValue> = {
    column: Column<TData, TValue>;
    title: string;
    className?: string;
};

/**
 * A column header that toggles server-side sorting when the column is
 * sortable, and renders as plain text otherwise.
 */
export function DataTableColumnHeader<TData, TValue>({
    column,
    title,
    className,
}: DataTableColumnHeaderProps<TData, TValue>) {
    // See DataTable: opt out of React Compiler so the sort indicator stays live.
    'use no memo';

    if (!column.getCanSort()) {
        return <span className={className}>{title}</span>;
    }

    const sorted = column.getIsSorted();

    return (
        <button
            type="button"
            onClick={() => column.toggleSorting(sorted === 'asc')}
            className={cn(
                'group -ml-1 inline-flex items-center gap-1 rounded px-1 py-0.5 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                className,
            )}
        >
            <span>{title}</span>
            {sorted === 'asc' ? (
                <ArrowUp className="size-3.5" />
            ) : sorted === 'desc' ? (
                <ArrowDown className="size-3.5" />
            ) : (
                <ChevronsUpDown className="size-3.5 opacity-50 group-hover:opacity-100" />
            )}
        </button>
    );
}
