import { Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useMemo } from 'react';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { select, store } from '@/routes/dt/organization';
import type { Paginated } from '@/types/ui';

type Organization = {
    id: number;
    name: string;
    rut: string | null;
};

type Props = {
    organizations: Paginated<Organization>;
    selectedId: number | null;
    filters: {
        search: string | null;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
};

export default function SelectOrganization({
    organizations,
    selectedId,
    filters,
}: Props) {
    const { t } = useTranslations();

    function selectOrganization(organizationId: number) {
        router.post(store().url, { organization_id: organizationId });
    }

    const columns = useMemo<ColumnDef<Organization>[]>(
        () => [
            {
                accessorKey: 'name',
                meta: { title: t('ui.dt.organization.select.columns.name') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.dt.organization.select.columns.name')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="font-medium">{row.original.name}</span>
                ),
            },
            {
                accessorKey: 'rut',
                meta: { title: t('ui.dt.organization.select.columns.rut') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.dt.organization.select.columns.rut')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="text-muted-foreground">
                        {row.original.rut ?? '—'}
                    </span>
                ),
            },
            {
                id: 'actions',
                enableHiding: false,
                enableSorting: false,
                meta: {
                    headClassName: 'text-right',
                    cellClassName: 'text-right',
                },
                header: () => null,
                cell: ({ row }) => {
                    const isSelected = row.original.id === selectedId;

                    return (
                        <Button
                            size="sm"
                            variant={isSelected ? 'secondary' : 'default'}
                            disabled={isSelected}
                            onClick={() => selectOrganization(row.original.id)}
                        >
                            {isSelected
                                ? t('ui.dt.organization.select.current')
                                : t('ui.dt.organization.select.submit')}
                        </Button>
                    );
                },
            },
        ],
        [t, selectedId],
    );

    return (
        <>
            <Head title={t('ui.dt.organization.select.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.dt.organization.select.title')}
                    description={t('ui.dt.organization.select.description')}
                />

                <DataTable
                    data={organizations}
                    columns={columns}
                    routeUrl={select().url}
                    filters={filters}
                    only={['organizations', 'filters']}
                    searchPlaceholder={t(
                        'ui.dt.organization.select.search_placeholder',
                    )}
                    emptyLabel={t('ui.dt.organization.select.no_results')}
                />
            </div>
        </>
    );
}
