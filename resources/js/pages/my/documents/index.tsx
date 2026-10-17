import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useMemo } from 'react';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import Heading from '@/components/heading';
import { useTranslations } from '@/hooks/use-translations';
import { toneChip } from '@/lib/status-tone';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/my/documents';
import type { Paginated } from '@/types/ui';

type Chip = { value: string; label: string; tone: string };

type MyDocument = {
    id: number;
    title: string;
    type: string | null;
    status: Chip;
    published_at: string | null;
    my_signature: { status: string; label: string; tone: string } | null;
    awaiting_me: boolean;
};

type Props = {
    documents: Paginated<MyDocument>;
    filters: {
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
};

const CHIP =
    'inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium';

export default function MyDocumentsIndex({ documents, filters }: Props) {
    const { t } = useTranslations();

    const columns = useMemo<ColumnDef<MyDocument>[]>(
        () => [
            {
                accessorKey: 'title',
                meta: { title: t('ui.documents.my.columns.title') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.documents.my.columns.title')}
                    />
                ),
                cell: ({ row }) => (
                    <Link
                        href={show(row.original.id).url}
                        className="flex items-center gap-2 font-medium hover:underline"
                    >
                        {row.original.title}
                        {row.original.awaiting_me && (
                            <span className="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-400">
                                {t('ui.documents.my.awaiting_you')}
                            </span>
                        )}
                    </Link>
                ),
            },
            {
                id: 'type',
                enableSorting: false,
                meta: {
                    title: t('ui.documents.my.columns.type'),
                    cellClassName: 'text-muted-foreground',
                },
                header: () => t('ui.documents.my.columns.type'),
                cell: ({ row }) => row.original.type ?? '—',
            },
            {
                id: 'status',
                enableSorting: false,
                meta: { title: t('ui.documents.my.columns.status') },
                header: () => t('ui.documents.my.columns.status'),
                cell: ({ row }) => (
                    <span
                        className={cn(CHIP, toneChip(row.original.status.tone))}
                    >
                        {row.original.status.label}
                    </span>
                ),
            },
            {
                id: 'my_signature',
                enableSorting: false,
                meta: { title: t('ui.documents.my.columns.my_signature') },
                header: () => t('ui.documents.my.columns.my_signature'),
                cell: ({ row }) =>
                    row.original.my_signature ? (
                        <span
                            className={cn(
                                CHIP,
                                toneChip(row.original.my_signature.tone),
                            )}
                        >
                            {row.original.my_signature.label}
                        </span>
                    ) : (
                        <span className="text-muted-foreground">—</span>
                    ),
            },
            {
                accessorKey: 'published_at',
                meta: {
                    title: t('ui.documents.my.columns.published_at'),
                    cellClassName: 'text-muted-foreground',
                },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.documents.my.columns.published_at')}
                    />
                ),
                cell: ({ row }) => row.original.published_at ?? '—',
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('ui.documents.my.title')} />

            <div className="space-y-5 p-6">
                <Heading
                    title={t('ui.documents.my.title')}
                    description={t('ui.documents.my.description')}
                />

                <DataTable
                    data={documents}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    only={['documents', 'filters']}
                    emptyLabel={t('ui.documents.my.empty')}
                />
            </div>
        </>
    );
}
