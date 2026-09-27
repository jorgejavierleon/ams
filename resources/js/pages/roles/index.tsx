import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { AvatarGroup } from '@/components/avatar-group';
import type { AvatarGroupUser } from '@/components/avatar-group';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import { DataTableRowActions } from '@/components/data-table-row-actions';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { create, destroy, index, show } from '@/routes/roles';
import type { Paginated } from '@/types/ui';

type Role = {
    id: number;
    name: string;
    label: string;
    permissions_count: number;
    users_count: number;
    avatars: AvatarGroupUser[];
};

type Props = {
    roles: Paginated<Role>;
    filters: {
        search: string | null;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
};

export default function RolesIndex({ roles, filters }: Props) {
    const { t } = useTranslations();
    const [deleteTarget, setDeleteTarget] = useState<Role | null>(null);
    const [deleting, setDeleting] = useState(false);

    const columns = useMemo<ColumnDef<Role>[]>(
        () => [
            {
                accessorKey: 'name',
                meta: { title: t('ui.roles.columns.role') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.roles.columns.role')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="font-medium">{row.original.label}</span>
                ),
            },
            {
                accessorKey: 'permissions_count',
                meta: { title: t('ui.roles.columns.permissions') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.roles.columns.permissions')}
                    />
                ),
                cell: ({ row }) => (
                    <Badge variant="secondary">
                        {row.original.permissions_count}
                    </Badge>
                ),
            },
            {
                accessorKey: 'users_count',
                meta: { title: t('ui.roles.columns.users') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.roles.columns.users')}
                    />
                ),
                cell: ({ row }) => (
                    <AvatarGroup
                        users={row.original.avatars}
                        total={row.original.users_count}
                    />
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
                        view={{
                            label: t('ui.roles.actions.manage'),
                            href: show(row.original.id).url,
                        }}
                        delete={{
                            label: t('ui.roles.actions.delete'),
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
            onStart: () => setDeleting(true),
            onFinish: () => {
                setDeleting(false);
                setDeleteTarget(null);
            },
        });
    }

    return (
        <>
            <Head title={t('ui.roles.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={t('ui.roles.title')}
                        description={t('ui.roles.description')}
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="size-4" />
                            {t('ui.roles.new')}
                        </Link>
                    </Button>
                </div>

                <DataTable
                    data={roles}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    only={['roles', 'filters']}
                    searchPlaceholder={t('ui.roles.search_placeholder')}
                    emptyLabel={t('ui.roles.empty')}
                />
            </div>

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title={t('ui.roles.delete_dialog.title')}
                description={
                    deleteTarget && deleteTarget.users_count > 0
                        ? t('ui.roles.delete_dialog.description_with_users', {
                              name: deleteTarget.label,
                              count: deleteTarget.users_count,
                          })
                        : t('ui.roles.delete_dialog.description', {
                              name: deleteTarget?.label ?? '',
                          })
                }
                confirmLabel={t('ui.roles.delete_dialog.confirm')}
                onConfirm={confirmDelete}
                processing={deleting}
            />
        </>
    );
}
