import { Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import { DataTableRowActions } from '@/components/data-table-row-actions';
import Heading from '@/components/heading';
import { useTranslations } from '@/hooks/use-translations';
import { destroy, index } from '@/routes/saas/leads';
import type { Paginated } from '@/types/ui';

type Lead = {
    id: number;
    name: string;
    company: string | null;
    email: string;
    message: string | null;
    created_at: string;
};

type Props = {
    leads: Paginated<Lead>;
    filters: {
        search: string | null;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
};

export default function LeadsIndex({ leads, filters }: Props) {
    const { t } = useTranslations();
    const [deleteTarget, setDeleteTarget] = useState<Lead | null>(null);

    const columns = useMemo<ColumnDef<Lead>[]>(
        () => [
            {
                accessorKey: 'name',
                meta: { title: t('ui.leads.columns.name') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.leads.columns.name')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="font-medium">{row.original.name}</span>
                ),
            },
            {
                accessorKey: 'company',
                meta: { title: t('ui.leads.columns.company') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.leads.columns.company')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="text-muted-foreground">
                        {row.original.company ?? '—'}
                    </span>
                ),
            },
            {
                accessorKey: 'email',
                meta: { title: t('ui.leads.columns.email') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.leads.columns.email')}
                    />
                ),
                cell: ({ row }) => (
                    <a
                        href={`mailto:${row.original.email}`}
                        className="text-sm hover:underline"
                    >
                        {row.original.email}
                    </a>
                ),
            },
            {
                accessorKey: 'message',
                enableSorting: false,
                meta: { title: t('ui.leads.columns.message') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.leads.columns.message')}
                    />
                ),
                cell: ({ row }) =>
                    row.original.message ? (
                        <span
                            className="line-clamp-2 max-w-xs text-sm text-muted-foreground"
                            title={row.original.message}
                        >
                            {row.original.message}
                        </span>
                    ) : (
                        <span className="text-muted-foreground">—</span>
                    ),
            },
            {
                accessorKey: 'created_at',
                meta: { title: t('ui.leads.columns.created') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.leads.columns.created')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="font-mono text-xs whitespace-nowrap text-muted-foreground">
                        {row.original.created_at}
                    </span>
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
                    <DataTableRowActions
                        delete={{
                            label: t('ui.leads.actions.delete'),
                            onClick: () => setDeleteTarget(row.original),
                        }}
                    />
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
            <Head title={t('ui.leads.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.leads.title')}
                    description={t('ui.leads.description')}
                />

                <DataTable
                    data={leads}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    only={['leads', 'filters']}
                    searchPlaceholder={t('ui.leads.search_placeholder')}
                    emptyLabel={t('ui.leads.empty')}
                />
            </div>

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title={t('ui.leads.delete_dialog.title')}
                description={t('ui.leads.delete_dialog.description', {
                    name: deleteTarget?.name ?? '',
                })}
                confirmLabel={t('ui.leads.delete_dialog.confirm')}
                onConfirm={confirmDelete}
            />
        </>
    );
}
