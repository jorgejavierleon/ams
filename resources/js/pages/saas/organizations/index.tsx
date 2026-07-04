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
import { create, destroy, edit, index } from '@/routes/saas/organizations';
import type { Paginated } from '@/types/ui';

type Organization = {
    id: number;
    name: string;
    slug: string;
    plan: string;
    users_count: number;
    created_at: string | null;
};

type Props = {
    organizations: Paginated<Organization>;
    filters: {
        search: string | null;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
};

export default function OrganizationsIndex({ organizations, filters }: Props) {
    const { t } = useTranslations();
    const [deleteTarget, setDeleteTarget] = useState<Organization | null>(null);

    const columns = useMemo<ColumnDef<Organization>[]>(
        () => [
            {
                accessorKey: 'name',
                meta: { title: t('ui.organizations.columns.name') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.organizations.columns.name')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="font-medium">{row.original.name}</span>
                ),
            },
            {
                accessorKey: 'slug',
                meta: { title: t('ui.organizations.columns.slug') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.organizations.columns.slug')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="text-muted-foreground">
                        {row.original.slug}
                    </span>
                ),
            },
            {
                accessorKey: 'plan',
                meta: { title: t('ui.organizations.columns.plan') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.organizations.columns.plan')}
                    />
                ),
                cell: ({ row }) => (
                    <Badge variant="secondary">{row.original.plan}</Badge>
                ),
            },
            {
                accessorKey: 'users_count',
                meta: { title: t('ui.organizations.columns.users') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.organizations.columns.users')}
                    />
                ),
                cell: ({ row }) => row.original.users_count,
            },
            {
                accessorKey: 'created_at',
                meta: { title: t('ui.organizations.columns.created') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.organizations.columns.created')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="text-muted-foreground">
                        {row.original.created_at ?? '—'}
                    </span>
                ),
            },
            {
                id: 'actions',
                enableHiding: false,
                meta: { headClassName: 'text-right', cellClassName: 'text-right' },
                header: () => null,
                cell: ({ row }) => (
                    <div className="flex justify-end gap-2">
                        <Link
                            href={edit(row.original.id)}
                            className="text-sm text-primary underline-offset-4 hover:underline"
                        >
                            {t('ui.organizations.actions.edit')}
                        </Link>
                        <button
                            type="button"
                            onClick={() => setDeleteTarget(row.original)}
                            className="text-sm text-destructive underline-offset-4 hover:underline"
                        >
                            {t('ui.organizations.actions.delete')}
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
            <Head title={t('ui.organizations.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={t('ui.organizations.title')}
                        description={t('ui.organizations.description')}
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="size-4" />
                            {t('ui.organizations.new')}
                        </Link>
                    </Button>
                </div>

                <DataTable
                    data={organizations}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    only={['organizations', 'filters']}
                    searchPlaceholder={t('ui.organizations.search_placeholder')}
                    emptyLabel={t('ui.organizations.empty')}
                />
            </div>

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title={t('ui.organizations.delete_dialog.title')}
                description={t('ui.organizations.delete_dialog.description', {
                    name: deleteTarget?.name ?? '',
                })}
                confirmLabel={t('ui.organizations.delete_dialog.confirm')}
                onConfirm={confirmDelete}
            />
        </>
    );
}
