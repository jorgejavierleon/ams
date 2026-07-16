import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useMemo, useState } from 'react';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import Heading from '@/components/heading';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/hooks/use-translations';
import { index } from '@/routes/dt/incidents';
import type { Paginated } from '@/types/ui';

type Incident = {
    id: number;
    start_time: string;
    end_time: string | null;
    duration: string | null;
    description: string;
};

type Props = {
    incidents: Paginated<Incident>;
    filters: {
        sort: string | null;
        direction: 'asc' | 'desc' | null;
        from: string | null;
        to: string | null;
    };
};

export default function IncidentsIndex({ incidents, filters }: Props) {
    const { t } = useTranslations();

    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    const extraParams = useMemo(
        () => ({
            from: from || undefined,
            to: to || undefined,
        }),
        [from, to],
    );

    const columns = useMemo<ColumnDef<Incident>[]>(
        () => [
            {
                accessorKey: 'start_time',
                meta: { title: t('ui.dt.incidents.columns.start_time') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.dt.incidents.columns.start_time')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="font-medium whitespace-nowrap">
                        {row.original.start_time}
                    </span>
                ),
            },
            {
                accessorKey: 'end_time',
                meta: { title: t('ui.dt.incidents.columns.end_time') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.dt.incidents.columns.end_time')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="whitespace-nowrap text-muted-foreground">
                        {row.original.end_time ??
                            t('ui.dt.incidents.ongoing')}
                    </span>
                ),
            },
            {
                accessorKey: 'duration',
                enableSorting: false,
                meta: { title: t('ui.dt.incidents.columns.duration') },
                header: () => t('ui.dt.incidents.columns.duration'),
                cell: ({ row }) => row.original.duration ?? '—',
            },
            {
                accessorKey: 'description',
                meta: { title: t('ui.dt.incidents.columns.description') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.dt.incidents.columns.description')}
                    />
                ),
                cell: ({ row }) => row.original.description,
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('ui.dt.incidents.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.dt.incidents.title')}
                    description={t('ui.dt.incidents.description')}
                />

                <DataTable
                    data={incidents}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    extraParams={extraParams}
                    only={['incidents', 'filters']}
                    emptyLabel={t('ui.dt.incidents.empty')}
                    toolbar={
                        <div className="flex flex-wrap items-center gap-2">
                            <Input
                                type="date"
                                value={from}
                                onChange={(event) =>
                                    setFrom(event.target.value)
                                }
                                aria-label={t('ui.dt.incidents.filters.from')}
                                className="w-[150px]"
                            />
                            <Input
                                type="date"
                                value={to}
                                onChange={(event) => setTo(event.target.value)}
                                aria-label={t('ui.dt.incidents.filters.to')}
                                className="w-[150px]"
                            />
                        </div>
                    }
                />
            </div>
        </>
    );
}
