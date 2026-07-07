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
import { create, destroy, edit, index } from '@/routes/companies';
import type { Paginated } from '@/types/ui';

type Company = {
    id: number;
    social_reason: string;
    rut: string;
    region: string | null;
    commune: string | null;
    users_count: number;
    is_active: boolean;
};

type Props = {
    companies: Paginated<Company>;
    filters: {
        search: string | null;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
};

export default function CompaniesIndex({ companies, filters }: Props) {
    const { t } = useTranslations();
    const [deleteTarget, setDeleteTarget] = useState<Company | null>(null);

    const columns = useMemo<ColumnDef<Company>[]>(
        () => [
            {
                accessorKey: 'social_reason',
                meta: { title: t('ui.companies.columns.name') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.companies.columns.name')}
                    />
                ),
                cell: ({ row }) => (
                    <Link
                        href={edit(row.original.id)}
                        className="font-medium text-primary underline-offset-4 hover:underline"
                    >
                        {row.original.social_reason}
                    </Link>
                ),
            },
            {
                accessorKey: 'rut',
                enableSorting: true,
                meta: { title: t('ui.companies.columns.rut') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.companies.columns.rut')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="text-muted-foreground">
                        {row.original.rut}
                    </span>
                ),
            },
            {
                accessorKey: 'region',
                enableSorting: false,
                meta: { title: t('ui.companies.columns.region') },
                header: () => t('ui.companies.columns.region'),
                cell: ({ row }) => row.original.region ?? '—',
            },
            {
                accessorKey: 'commune',
                enableSorting: false,
                meta: { title: t('ui.companies.columns.commune') },
                header: () => t('ui.companies.columns.commune'),
                cell: ({ row }) => row.original.commune ?? '—',
            },
            {
                accessorKey: 'users_count',
                meta: { title: t('ui.companies.columns.employees') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.companies.columns.employees')}
                    />
                ),
                cell: ({ row }) => (
                    <Badge variant="secondary">
                        {row.original.users_count}
                    </Badge>
                ),
            },
            {
                accessorKey: 'is_active',
                enableSorting: false,
                meta: { title: t('ui.companies.columns.status') },
                header: () => t('ui.companies.columns.status'),
                cell: ({ row }) => (
                    <Badge
                        variant={row.original.is_active ? 'default' : 'outline'}
                    >
                        {t(
                            row.original.is_active
                                ? 'ui.companies.status.active'
                                : 'ui.companies.status.inactive',
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
                            {t('ui.companies.actions.edit')}
                        </Link>
                        <button
                            type="button"
                            onClick={() => setDeleteTarget(row.original)}
                            className="text-sm text-destructive underline-offset-4 hover:underline"
                        >
                            {t('ui.companies.actions.delete')}
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
            <Head title={t('ui.companies.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={t('ui.companies.title')}
                        description={t('ui.companies.description')}
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="size-4" />
                            {t('ui.companies.new')}
                        </Link>
                    </Button>
                </div>

                <DataTable
                    data={companies}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    only={['companies', 'filters']}
                    searchPlaceholder={t('ui.companies.search_placeholder')}
                    emptyLabel={t('ui.companies.empty')}
                />
            </div>

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title={t('ui.companies.delete_dialog.title')}
                description={t('ui.companies.delete_dialog.description', {
                    name: deleteTarget?.social_reason ?? '',
                })}
                confirmLabel={t('ui.companies.delete_dialog.confirm')}
                onConfirm={confirmDelete}
            />
        </>
    );
}
