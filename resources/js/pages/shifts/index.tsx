import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Plus, TriangleAlert } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { create, destroy, edit, index } from '@/routes/shifts';
import type { Paginated } from '@/types/ui';

type Shift = {
    id: number;
    name: string;
    type: string;
    total_week_hours: number | null;
    exceeds_max: boolean;
    assignments_count: number;
    is_default: boolean;
};

type Props = {
    shifts: Paginated<Shift>;
    filters: {
        search: string | null;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
};

export default function ShiftsIndex({ shifts, filters }: Props) {
    const { t } = useTranslations();
    const [deleteTarget, setDeleteTarget] = useState<Shift | null>(null);

    const columns = useMemo<ColumnDef<Shift>[]>(
        () => [
            {
                accessorKey: 'name',
                meta: { title: t('ui.shifts.columns.name') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.shifts.columns.name')}
                    />
                ),
                cell: ({ row }) => (
                    <div className="flex items-center gap-2">
                        <Link
                            href={edit(row.original.id)}
                            className="font-medium text-primary underline-offset-4 hover:underline"
                        >
                            {row.original.name}
                        </Link>
                        {row.original.is_default && (
                            <Badge variant="secondary">
                                {t('ui.shifts.default')}
                            </Badge>
                        )}
                    </div>
                ),
            },
            {
                accessorKey: 'type',
                enableSorting: true,
                meta: { title: t('ui.shifts.columns.type') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.shifts.columns.type')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="text-muted-foreground">
                        {row.original.type}
                    </span>
                ),
            },
            {
                accessorKey: 'total_week_hours',
                meta: { title: t('ui.shifts.columns.weekly_hours') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.shifts.columns.weekly_hours')}
                    />
                ),
                cell: ({ row }) => (
                    <span
                        className={
                            row.original.exceeds_max
                                ? 'inline-flex items-center gap-1 font-medium text-amber-600 dark:text-amber-500'
                                : ''
                        }
                    >
                        {row.original.total_week_hours ?? 0}
                        {row.original.exceeds_max && (
                            <TriangleAlert className="size-3.5" />
                        )}
                    </span>
                ),
            },
            {
                accessorKey: 'assignments_count',
                enableSorting: false,
                meta: { title: t('ui.shifts.columns.assignments') },
                header: () => t('ui.shifts.columns.assignments'),
                cell: ({ row }) => (
                    <Badge variant="secondary">
                        {row.original.assignments_count}
                    </Badge>
                ),
            },
            {
                id: 'actions',
                enableHiding: false,
                meta: {
                    headClassName: 'text-right',
                    cellClassName: 'text-right',
                },
                header: () => null,
                cell: ({ row }) => (
                    <div className="flex justify-end gap-2">
                        <Link
                            href={edit(row.original.id)}
                            className="text-sm text-primary underline-offset-4 hover:underline"
                        >
                            {t('ui.shifts.actions.edit')}
                        </Link>
                        <button
                            type="button"
                            onClick={() => setDeleteTarget(row.original)}
                            className="text-sm text-destructive underline-offset-4 hover:underline"
                        >
                            {t('ui.shifts.actions.delete')}
                        </button>
                    </div>
                ),
            },
        ],
        [t],
    );

    function confirmDelete() {
        if (!deleteTarget) {
            return;
        }

        router.delete(destroy(deleteTarget.id).url, {
            preserveScroll: true,
            onFinish: () => setDeleteTarget(null),
        });
    }

    return (
        <>
            <Head title={t('ui.shifts.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={t('ui.shifts.title')}
                        description={t('ui.shifts.description')}
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="size-4" />
                            {t('ui.shifts.new')}
                        </Link>
                    </Button>
                </div>

                <DataTable
                    data={shifts}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    only={['shifts', 'filters']}
                    searchPlaceholder={t('ui.shifts.search_placeholder')}
                    emptyLabel={t('ui.shifts.empty')}
                />
            </div>

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title={t('ui.shifts.delete_dialog.title')}
                description={t('ui.shifts.delete_dialog.description', {
                    name: deleteTarget?.name ?? '',
                })}
                confirmLabel={t('ui.shifts.delete_dialog.confirm')}
                onConfirm={confirmDelete}
            />
        </>
    );
}
