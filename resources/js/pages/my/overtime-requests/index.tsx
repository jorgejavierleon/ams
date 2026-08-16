import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Eye, MoreVertical, Plus } from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/hooks/use-translations';
import { toneChip } from '@/lib/status-tone';
import { cn } from '@/lib/utils';
import { create, index } from '@/routes/my/overtime-requests';
import type { Paginated } from '@/types/ui';

type OvertimeRequestRow = {
    id: number;
    date: string;
    requested_hours: string;
    reason: string | null;
    status: string;
    status_label: string;
    status_badge: string;
    reviewed_by: string | null;
    decision_reason: string | null;
    created_at: string | null;
};

type Option = { value: string; label: string };

type Props = {
    requests: Paginated<OvertimeRequestRow>;
    filters: {
        status: string | null;
        from: string | null;
        to: string | null;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
    statusOptions: Option[];
};

/** Drop the seconds from a stored `HH:MM:SS` figure for compact display. */
function hm(time: string): string {
    return time.slice(0, 5);
}

function DetailRow({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-4">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-sm font-medium">{value}</dd>
        </div>
    );
}

export default function MyOvertimeRequestsIndex({
    requests,
    filters,
    statusOptions,
}: Props) {
    const { t } = useTranslations();

    const [status, setStatus] = useState(filters.status ?? 'all');
    const [viewTarget, setViewTarget] = useState<OvertimeRequestRow | null>(
        null,
    );

    const extraParams = useMemo(
        () => ({
            status: status === 'all' ? undefined : status,
        }),
        [status],
    );

    const statusTabs = useMemo(
        () => [
            { value: 'all', label: t('ui.overtime.requests.my.tabs.all') },
            ...statusOptions,
        ],
        [statusOptions, t],
    );

    const columns = useMemo<ColumnDef<OvertimeRequestRow>[]>(
        () => [
            {
                accessorKey: 'date',
                meta: { title: t('ui.overtime.requests.my.columns.date') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.overtime.requests.my.columns.date')}
                    />
                ),
                cell: ({ row }) => row.original.date,
            },
            {
                id: 'requested_hours',
                meta: {
                    title: t('ui.overtime.requests.my.columns.requested_hours'),
                    cellClassName: 'tabular-nums',
                },
                header: () =>
                    t('ui.overtime.requests.my.columns.requested_hours'),
                cell: ({ row }) => hm(row.original.requested_hours),
            },
            {
                id: 'status',
                enableSorting: false,
                meta: { title: t('ui.overtime.requests.my.columns.status') },
                header: () => t('ui.overtime.requests.my.columns.status'),
                cell: ({ row }) => (
                    <span
                        className={cn(
                            'inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium',
                            toneChip(row.original.status_badge),
                        )}
                    >
                        {row.original.status_label}
                    </span>
                ),
            },
            {
                id: 'reviewed_by',
                enableSorting: false,
                meta: {
                    title: t('ui.overtime.requests.my.columns.reviewed_by'),
                },
                header: () => t('ui.overtime.requests.my.columns.reviewed_by'),
                cell: ({ row }) => row.original.reviewed_by ?? '—',
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
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t(
                                    'ui.overtime.requests.my.detail.title',
                                )}
                            >
                                <MoreVertical className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-40">
                            <DropdownMenuItem
                                onSelect={() => setViewTarget(row.original)}
                            >
                                <Eye className="size-4" />
                                {t('ui.overtime.requests.my.detail.title')}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                ),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('ui.overtime.requests.my.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={t('ui.overtime.requests.my.title')}
                        description={t('ui.overtime.requests.my.description')}
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="size-4" />
                            {t('ui.overtime.requests.my.new')}
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-1 rounded-lg bg-muted p-1">
                    {statusTabs.map((tab) => (
                        <button
                            key={tab.value}
                            type="button"
                            onClick={() => setStatus(tab.value)}
                            className={cn(
                                'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                status === tab.value
                                    ? 'bg-background text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>

                <DataTable
                    data={requests}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    extraParams={extraParams}
                    only={['requests', 'filters']}
                    emptyLabel={t('ui.overtime.requests.my.empty')}
                />
            </div>

            <Dialog
                open={viewTarget !== null}
                onOpenChange={(open) => !open && setViewTarget(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('ui.overtime.requests.my.detail.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {viewTarget?.date}
                        </DialogDescription>
                    </DialogHeader>

                    {viewTarget && (
                        <div className="flex flex-col gap-4 py-2">
                            <dl className="grid gap-3">
                                <DetailRow
                                    label={t(
                                        'ui.overtime.requests.my.detail.status',
                                    )}
                                    value={
                                        <span
                                            className={cn(
                                                'inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium',
                                                toneChip(
                                                    viewTarget.status_badge,
                                                ),
                                            )}
                                        >
                                            {viewTarget.status_label}
                                        </span>
                                    }
                                />
                                <DetailRow
                                    label={t(
                                        'ui.overtime.requests.my.detail.requested_hours',
                                    )}
                                    value={hm(viewTarget.requested_hours)}
                                />
                                <DetailRow
                                    label={t(
                                        'ui.overtime.requests.my.detail.reason',
                                    )}
                                    value={
                                        viewTarget.reason ||
                                        t(
                                            'ui.overtime.requests.my.detail.no_reason',
                                        )
                                    }
                                />
                                {viewTarget.status !== 'pending' && (
                                    <DetailRow
                                        label={t(
                                            'ui.overtime.requests.my.detail.decision_reason',
                                        )}
                                        value={
                                            viewTarget.decision_reason ||
                                            t(
                                                'ui.overtime.requests.my.detail.no_reason',
                                            )
                                        }
                                    />
                                )}
                                <DetailRow
                                    label={t(
                                        'ui.overtime.requests.my.detail.reviewed_by',
                                    )}
                                    value={
                                        viewTarget.reviewed_by ??
                                        t('ui.overtime.requests.my.detail.none')
                                    }
                                />
                                <DetailRow
                                    label={t(
                                        'ui.overtime.requests.my.detail.created_at',
                                    )}
                                    value={
                                        viewTarget.created_at ??
                                        t('ui.overtime.requests.my.detail.none')
                                    }
                                />
                            </dl>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
