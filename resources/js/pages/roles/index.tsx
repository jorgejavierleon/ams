import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useMemo } from 'react';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/hooks/use-translations';
import { index, show } from '@/routes/roles';
import type { Paginated } from '@/types/ui';

type Role = {
    id: number;
    name: string;
    permissions_count: number;
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
                    <span className="font-medium capitalize">
                        {row.original.name}
                    </span>
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
                id: 'actions',
                enableHiding: false,
                meta: { headClassName: 'text-right', cellClassName: 'text-right' },
                header: () => null,
                cell: ({ row }) => (
                    <Link
                        href={show(row.original.id)}
                        className="text-sm text-primary underline-offset-4 hover:underline"
                    >
                        {t('ui.roles.actions.manage')}
                    </Link>
                ),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('ui.roles.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.roles.title')}
                    description={t('ui.roles.description')}
                />

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
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Roles',
            href: index(),
        },
    ],
};
