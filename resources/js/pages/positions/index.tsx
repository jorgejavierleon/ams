import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import Heading from '@/components/heading';
import PositionFormDialog from '@/components/position-form-dialog';
import type { PositionFormTarget } from '@/components/position-form-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { destroy, index, show } from '@/routes/positions';
import type { Paginated } from '@/types/ui';

type Position = {
    id: number;
    name: string;
    active_users_count: number;
};

type Props = {
    positions: Paginated<Position>;
    filters: {
        search: string | null;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
};

export default function PositionsIndex({ positions, filters }: Props) {
    const { t } = useTranslations();
    const [formOpen, setFormOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<PositionFormTarget>(null);
    const [deleteTarget, setDeleteTarget] = useState<Position | null>(null);

    const columns = useMemo<ColumnDef<Position>[]>(
        () => [
            {
                accessorKey: 'name',
                meta: { title: t('ui.positions.columns.name') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.positions.columns.name')}
                    />
                ),
                cell: ({ row }) => (
                    <Link
                        href={show(row.original.id)}
                        className="font-medium text-primary underline-offset-4 hover:underline"
                    >
                        {row.original.name}
                    </Link>
                ),
            },
            {
                accessorKey: 'active_users_count',
                meta: { title: t('ui.positions.columns.employees') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.positions.columns.employees')}
                    />
                ),
                cell: ({ row }) => (
                    <Badge variant="secondary">
                        {row.original.active_users_count}
                    </Badge>
                ),
            },
            {
                id: 'actions',
                enableHiding: false,
                meta: { headClassName: 'text-right', cellClassName: 'text-right' },
                header: () => null,
                cell: ({ row }) => (
                    <div className="flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={() => {
                                setEditTarget({
                                    id: row.original.id,
                                    name: row.original.name,
                                });
                                setFormOpen(true);
                            }}
                            className="text-sm text-primary underline-offset-4 hover:underline"
                        >
                            {t('ui.positions.actions.edit')}
                        </button>
                        <button
                            type="button"
                            onClick={() => setDeleteTarget(row.original)}
                            className="text-sm text-destructive underline-offset-4 hover:underline"
                        >
                            {t('ui.positions.actions.delete')}
                        </button>
                    </div>
                ),
            },
        ],
        [t],
    );

    function openCreate() {
        setEditTarget(null);
        setFormOpen(true);
    }

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
            <Head title={t('ui.positions.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={t('ui.positions.title')}
                        description={t('ui.positions.description')}
                    />
                    <Button onClick={openCreate}>
                        <Plus className="size-4" />
                        {t('ui.positions.new')}
                    </Button>
                </div>

                <DataTable
                    data={positions}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    only={['positions', 'filters']}
                    searchPlaceholder={t('ui.positions.search_placeholder')}
                    emptyLabel={t('ui.positions.empty')}
                />
            </div>

            <PositionFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                position={editTarget}
            />

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title={t('ui.positions.delete_dialog.title')}
                description={t('ui.positions.delete_dialog.description', {
                    name: deleteTarget?.name ?? '',
                })}
                confirmLabel={t('ui.positions.delete_dialog.confirm')}
                onConfirm={confirmDelete}
            />
        </>
    );
}
