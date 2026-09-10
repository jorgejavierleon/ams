import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { ArrowLeft, ArrowRight } from 'lucide-react';
import { useMemo } from 'react';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { index as overtimeIndex } from '@/routes/overtime';
import { index } from '@/routes/overtime/pending';
import type { Paginated } from '@/types/ui';

type PendingRecord = {
    id: number;
    employee: string | null;
    supervisor: string | null;
    date: string;
    days_pending: number;
    calculated_hours: string | null;
    workday_url: string;
};

type Props = {
    records: Paginated<PendingRecord>;
    filters: {
        search: string | null;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
    stats: {
        threshold_days: number;
        stale_count: number;
        average_resolution_days: number | null;
    };
};

export default function OvertimePendingIndex({
    records,
    filters,
    stats,
}: Props) {
    const { t } = useTranslations();

    const columns = useMemo<ColumnDef<PendingRecord>[]>(
        () => [
            {
                accessorKey: 'employee',
                meta: { title: t('ui.overtime.pending.columns.employee') },
                header: () => t('ui.overtime.pending.columns.employee'),
                cell: ({ row }) => (
                    <span className="font-medium">
                        {row.original.employee}
                    </span>
                ),
            },
            {
                accessorKey: 'supervisor',
                meta: { title: t('ui.overtime.pending.columns.supervisor') },
                header: () => t('ui.overtime.pending.columns.supervisor'),
                cell: ({ row }) =>
                    row.original.supervisor ??
                    t('ui.overtime.pending.no_supervisor'),
            },
            {
                accessorKey: 'date',
                meta: { title: t('ui.overtime.pending.columns.date') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.overtime.pending.columns.date')}
                    />
                ),
                cell: ({ row }) => row.original.date,
            },
            {
                accessorKey: 'days_pending',
                meta: { title: t('ui.overtime.pending.columns.days_pending') },
                header: () => t('ui.overtime.pending.columns.days_pending'),
                cell: ({ row }) => (
                    <Badge variant="destructive">
                        {t('ui.overtime.pending.days_pending_value', {
                            days: row.original.days_pending,
                        })}
                    </Badge>
                ),
            },
            {
                accessorKey: 'calculated_hours',
                meta: {
                    title: t('ui.overtime.pending.columns.calculated_hours'),
                },
                header: () =>
                    t('ui.overtime.pending.columns.calculated_hours'),
                cell: ({ row }) => row.original.calculated_hours,
            },
            {
                id: 'actions',
                header: () => null,
                cell: ({ row }) => (
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={row.original.workday_url}>
                            {t('ui.overtime.pending.resolve')}
                            <ArrowRight className="size-4" />
                        </Link>
                    </Button>
                ),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('ui.overtime.pending.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={overtimeIndex()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <Heading
                        title={t('ui.overtime.pending.title')}
                        description={t('ui.overtime.pending.description')}
                    />
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <StatTile
                        label={t('ui.overtime.pending.stats.stale_count')}
                        value={stats.stale_count}
                    />
                    <StatTile
                        label={t('ui.overtime.pending.stats.threshold_days')}
                        value={t('ui.overtime.pending.days_pending_value', {
                            days: stats.threshold_days,
                        })}
                    />
                    <StatTile
                        label={t(
                            'ui.overtime.pending.stats.average_resolution_days',
                        )}
                        value={
                            stats.average_resolution_days === null
                                ? t('ui.overtime.pending.stats.no_data')
                                : t(
                                      'ui.overtime.pending.days_pending_value',
                                      { days: stats.average_resolution_days },
                                  )
                        }
                    />
                </div>

                <DataTable
                    data={records}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    only={['records', 'filters', 'stats']}
                    searchPlaceholder={t(
                        'ui.overtime.pending.search_placeholder',
                    )}
                    emptyLabel={t('ui.overtime.pending.empty')}
                />
            </div>
        </>
    );
}

function StatTile({
    label,
    value,
}: {
    label: string;
    value: string | number;
}) {
    return (
        <div className="rounded-xl border bg-card p-4 shadow-xs">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="text-2xl font-semibold">{value}</p>
        </div>
    );
}
