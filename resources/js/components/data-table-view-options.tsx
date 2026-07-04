import type { Table } from '@tanstack/react-table';
import { SlidersHorizontal } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/hooks/use-translations';

type DataTableViewOptionsProps<TData> = {
    table: Table<TData>;
};

/**
 * Dropdown to toggle visibility of hideable columns. Columns opt in by
 * exposing a string `meta.title` in their definition (used as the label).
 */
export function DataTableViewOptions<TData>({
    table,
}: DataTableViewOptionsProps<TData>) {
    // See DataTable: opt out of React Compiler so column visibility reads stay live.
    'use no memo';

    const { t } = useTranslations();

    const columns = table
        .getAllColumns()
        .filter(
            (column) =>
                typeof column.accessorFn !== 'undefined' &&
                column.getCanHide(),
        );

    if (columns.length === 0) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm">
                    <SlidersHorizontal className="size-4" />
                    {t('ui.common.data_table.toggle_columns')}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
                <DropdownMenuLabel>
                    {t('ui.common.data_table.toggle_columns')}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {columns.map((column) => {
                    const title =
                        (column.columnDef.meta as { title?: string })?.title ??
                        column.id;

                    return (
                        <DropdownMenuCheckboxItem
                            key={column.id}
                            className="capitalize"
                            checked={column.getIsVisible()}
                            onCheckedChange={(value) =>
                                column.toggleVisibility(!!value)
                            }
                        >
                            {title}
                        </DropdownMenuCheckboxItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
