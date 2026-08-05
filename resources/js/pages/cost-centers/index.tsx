import { Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import CostCenterFormDialog from '@/components/cost-center-form-dialog';
import type { CostCenterFormTarget } from '@/components/cost-center-form-dialog';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { destroy, index } from '@/routes/cost-centers';
import type { Paginated } from '@/types/ui';

type CostCenter = {
    id: number;
    name: string;
    code: string | null;
    active_users_count: number;
};

type Props = {
    costCenters: Paginated<CostCenter>;
    filters: {
        search: string | null;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
};

export default function CostCentersIndex({ costCenters, filters }: Props) {
    const { t } = useTranslations();
    const [formOpen, setFormOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<CostCenterFormTarget>(null);
    const [deleteTarget, setDeleteTarget] = useState<CostCenter | null>(null);

    const columns = useMemo<ColumnDef<CostCenter>[]>(
        () => [
            {
                accessorKey: 'name',
                meta: { title: t('ui.cost_centers.columns.name') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.cost_centers.columns.name')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="font-medium">{row.original.name}</span>
                ),
            },
            {
                accessorKey: 'code',
                meta: { title: t('ui.cost_centers.columns.code') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.cost_centers.columns.code')}
                    />
                ),
                cell: ({ row }) =>
                    row.original.code ?? (
                        <span className="text-muted-foreground">—</span>
                    ),
            },
            {
                accessorKey: 'active_users_count',
                meta: { title: t('ui.cost_centers.columns.employees') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.cost_centers.columns.employees')}
                    />
                ),
                cell: ({ row }) => row.original.active_users_count,
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
                        <button
                            type="button"
                            onClick={() => {
                                setEditTarget({
                                    id: row.original.id,
                                    name: row.original.name,
                                    code: row.original.code,
                                });
                                setFormOpen(true);
                            }}
                            className="text-sm text-primary underline-offset-4 hover:underline"
                        >
                            {t('ui.cost_centers.actions.edit')}
                        </button>
                        <button
                            type="button"
                            onClick={() => setDeleteTarget(row.original)}
                            className="text-sm text-destructive underline-offset-4 hover:underline"
                        >
                            {t('ui.cost_centers.actions.delete')}
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
            <Head title={t('ui.cost_centers.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={t('ui.cost_centers.title')}
                        description={t('ui.cost_centers.description')}
                    />
                    <Button onClick={openCreate}>
                        <Plus className="size-4" />
                        {t('ui.cost_centers.new')}
                    </Button>
                </div>

                <DataTable
                    data={costCenters}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    only={['costCenters', 'filters']}
                    searchPlaceholder={t('ui.cost_centers.search_placeholder')}
                    emptyLabel={t('ui.cost_centers.empty')}
                />
            </div>

            <CostCenterFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                costCenter={editTarget}
            />

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title={t('ui.cost_centers.delete_dialog.title')}
                description={t('ui.cost_centers.delete_dialog.description', {
                    name: deleteTarget?.name ?? '',
                })}
                confirmLabel={t('ui.cost_centers.delete_dialog.confirm')}
                onConfirm={confirmDelete}
            />
        </>
    );
}
