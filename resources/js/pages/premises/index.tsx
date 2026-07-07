import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { create, destroy, edit, index } from '@/routes/premises';
import type { Paginated } from '@/types/ui';

type Premise = {
    id: number;
    name: string;
    company: string | null;
    address: string | null;
    has_coordinates: boolean;
};

type Props = {
    premises: Paginated<Premise>;
    filters: {
        search: string | null;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
};

export default function PremisesIndex({ premises, filters }: Props) {
    const { t } = useTranslations();
    const [deleteTarget, setDeleteTarget] = useState<Premise | null>(null);

    const columns = useMemo<ColumnDef<Premise>[]>(
        () => [
            {
                accessorKey: 'name',
                meta: { title: t('ui.premises.columns.name') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.premises.columns.name')}
                    />
                ),
                cell: ({ row }) => (
                    <Link
                        href={edit(row.original.id)}
                        className="font-medium text-primary underline-offset-4 hover:underline"
                    >
                        {row.original.name}
                    </Link>
                ),
            },
            {
                accessorKey: 'company',
                enableSorting: false,
                meta: { title: t('ui.premises.columns.company') },
                header: () => t('ui.premises.columns.company'),
                cell: ({ row }) => row.original.company ?? '—',
            },
            {
                accessorKey: 'address',
                meta: { title: t('ui.premises.columns.address') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.premises.columns.address')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="text-muted-foreground">
                        {row.original.address ?? '—'}
                    </span>
                ),
            },
            {
                accessorKey: 'has_coordinates',
                enableSorting: false,
                meta: { title: t('ui.premises.columns.coordinates') },
                header: () => t('ui.premises.columns.coordinates'),
                cell: ({ row }) => (
                    <Badge
                        variant={
                            row.original.has_coordinates ? 'default' : 'outline'
                        }
                    >
                        {t(
                            row.original.has_coordinates
                                ? 'ui.premises.coordinates.set'
                                : 'ui.premises.coordinates.unset',
                        )}
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
                            {t('ui.premises.actions.edit')}
                        </Link>
                        <button
                            type="button"
                            onClick={() => setDeleteTarget(row.original)}
                            className="text-sm text-destructive underline-offset-4 hover:underline"
                        >
                            {t('ui.premises.actions.delete')}
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
            <Head title={t('ui.premises.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={t('ui.premises.title')}
                        description={t('ui.premises.description')}
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="size-4" />
                            {t('ui.premises.new')}
                        </Link>
                    </Button>
                </div>

                <DataTable
                    data={premises}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    only={['premises', 'filters']}
                    searchPlaceholder={t('ui.premises.search_placeholder')}
                    emptyLabel={t('ui.premises.empty')}
                />
            </div>

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title={t('ui.premises.delete_dialog.title')}
                description={t('ui.premises.delete_dialog.description', {
                    name: deleteTarget?.name ?? '',
                })}
                confirmLabel={t('ui.premises.delete_dialog.confirm')}
                onConfirm={confirmDelete}
            />
        </>
    );
}
