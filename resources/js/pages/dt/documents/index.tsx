import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useMemo } from 'react';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/hooks/use-translations';
import { index, show } from '@/routes/dt/documents';
import type { Paginated } from '@/types/ui';

type StatusBadge = {
    value: string;
    label: string;
    variant: 'default' | 'secondary' | 'destructive' | 'outline';
};

type DocumentRow = {
    id: number;
    title: string;
    type: string | null;
    employee: string | null;
    status: StatusBadge;
    published_at: string | null;
    signed_at: string | null;
};

type Props = {
    documents: Paginated<DocumentRow>;
    filters: {
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
};

export default function DocumentsIndex({ documents, filters }: Props) {
    const { t } = useTranslations();

    const columns = useMemo<ColumnDef<DocumentRow>[]>(
        () => [
            {
                id: 'employee',
                enableSorting: false,
                meta: { title: t('ui.dt.documents.columns.employee') },
                header: () => t('ui.dt.documents.columns.employee'),
                cell: ({ row }) => (
                    <Link
                        href={show(row.original.id)}
                        className="font-medium text-primary underline-offset-4 hover:underline"
                    >
                        {row.original.employee ?? '—'}
                    </Link>
                ),
            },
            {
                id: 'type',
                enableSorting: false,
                meta: { title: t('ui.dt.documents.columns.type') },
                header: () => t('ui.dt.documents.columns.type'),
                cell: ({ row }) => row.original.type ?? '—',
            },
            {
                accessorKey: 'status',
                meta: { title: t('ui.dt.documents.columns.status') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.dt.documents.columns.status')}
                    />
                ),
                cell: ({ row }) => (
                    <Badge variant={row.original.status.variant}>
                        {row.original.status.label}
                    </Badge>
                ),
            },
            {
                accessorKey: 'published_at',
                meta: { title: t('ui.dt.documents.columns.published_at') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.dt.documents.columns.published_at')}
                    />
                ),
                cell: ({ row }) => row.original.published_at ?? '—',
            },
            {
                accessorKey: 'signed_at',
                meta: { title: t('ui.dt.documents.columns.signed_at') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.dt.documents.columns.signed_at')}
                    />
                ),
                cell: ({ row }) => row.original.signed_at ?? '—',
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('ui.dt.documents.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.dt.documents.title')}
                    description={t('ui.dt.documents.description')}
                />

                <DataTable
                    data={documents}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    only={['documents', 'filters']}
                    emptyLabel={t('ui.dt.documents.empty')}
                />
            </div>
        </>
    );
}
