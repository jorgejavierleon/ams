import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Eye, MoreVertical, Plus, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { create, destroy, index } from '@/routes/my/leaves';
import type { Paginated } from '@/types/ui';

type Leave = {
    id: number;
    type: string;
    type_label: string;
    start_date: string;
    end_date: string;
    half_day: boolean;
    half_day_type: string | null;
    business_days_requested: number;
    status: string;
    status_label: string;
    approved_by: string | null;
    is_medical: boolean;
    medical_leave_number: string | null;
    medical_leave_doctor: string | null;
    notes: string | null;
    created_at: string | null;
};

type Option = { value: string; label: string };

type VacationBalance = {
    used: number;
    available: number;
    total: number;
};

type Props = {
    leaves: Paginated<Leave>;
    filters: {
        status: string | null;
        from: string | null;
        to: string | null;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
    statusOptions: Option[];
    vacationBalance: VacationBalance;
};

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'destructive'> =
    {
        pending: 'secondary',
        approved: 'default',
        rejected: 'destructive',
    };

function DetailRow({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-4">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-sm font-medium">{value}</dd>
        </div>
    );
}

export default function MyLeavesIndex({
    leaves,
    filters,
    statusOptions,
    vacationBalance,
}: Props) {
    const { t } = useTranslations();

    const [status, setStatus] = useState(filters.status ?? 'all');
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    const [viewTarget, setViewTarget] = useState<Leave | null>(null);
    const [cancelTarget, setCancelTarget] = useState<Leave | null>(null);

    const extraParams = useMemo(
        () => ({
            status: status === 'all' ? undefined : status,
            from: from || undefined,
            to: to || undefined,
        }),
        [status, from, to],
    );

    const statusTabs = useMemo(
        () => [
            { value: 'all', label: t('ui.leaves.tabs.all') },
            ...statusOptions,
        ],
        [statusOptions, t],
    );

    function confirmCancel() {
        if (!cancelTarget) {
            return;
        }

        router.delete(destroy(cancelTarget.id).url, {
            preserveScroll: true,
            onFinish: () => setCancelTarget(null),
        });
    }

    const columns = useMemo<ColumnDef<Leave>[]>(
        () => [
            {
                id: 'type',
                enableSorting: false,
                meta: { title: t('ui.leaves.columns.type') },
                header: () => t('ui.leaves.columns.type'),
                cell: ({ row }) => (
                    <Badge variant="outline">{row.original.type_label}</Badge>
                ),
            },
            {
                accessorKey: 'start_date',
                meta: { title: t('ui.leaves.columns.start_date') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.leaves.columns.start_date')}
                    />
                ),
                cell: ({ row }) => row.original.start_date,
            },
            {
                accessorKey: 'end_date',
                meta: { title: t('ui.leaves.columns.end_date') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.leaves.columns.end_date')}
                    />
                ),
                cell: ({ row }) => row.original.end_date,
            },
            {
                id: 'half_day',
                enableSorting: false,
                meta: { title: t('ui.leaves.columns.half_day') },
                header: () => t('ui.leaves.columns.half_day'),
                cell: ({ row }) =>
                    row.original.half_day && row.original.half_day_type ? (
                        <span className="text-muted-foreground">
                            {t(
                                `ui.leaves.half_day_types.${row.original.half_day_type}`,
                            )}
                        </span>
                    ) : (
                        '—'
                    ),
            },
            {
                accessorKey: 'business_days_requested',
                meta: {
                    title: t('ui.leaves.columns.days'),
                    headClassName: 'text-right',
                    cellClassName: 'text-right tabular-nums',
                },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.leaves.columns.days')}
                    />
                ),
                cell: ({ row }) => row.original.business_days_requested,
            },
            {
                id: 'status',
                enableSorting: false,
                meta: { title: t('ui.leaves.columns.status') },
                header: () => t('ui.leaves.columns.status'),
                cell: ({ row }) => (
                    <Badge
                        variant={
                            STATUS_VARIANT[row.original.status] ?? 'outline'
                        }
                    >
                        {row.original.status_label}
                    </Badge>
                ),
            },
            {
                id: 'approved_by',
                enableSorting: false,
                meta: { title: t('ui.leaves.columns.approved_by') },
                header: () => t('ui.leaves.columns.approved_by'),
                cell: ({ row }) => row.original.approved_by ?? '—',
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
                        {row.original.status === 'pending' && (
                            <Button
                                variant="ghost"
                                size="icon"
                                className="text-destructive hover:text-destructive"
                                onClick={() => setCancelTarget(row.original)}
                                aria-label={t('ui.leaves.actions.cancel')}
                            >
                                <X className="size-4" />
                            </Button>
                        )}
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('ui.leaves.actions.more')}
                                >
                                    <MoreVertical className="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-40">
                                <DropdownMenuItem
                                    onSelect={() => setViewTarget(row.original)}
                                >
                                    <Eye className="size-4" />
                                    {t('ui.leaves.actions.view')}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                ),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('ui.leaves.my.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={t('ui.leaves.my.title')}
                        description={t('ui.leaves.my.description')}
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="size-4" />
                            {t('ui.leaves.my.new')}
                        </Link>
                    </Button>
                </div>

                <Card className="max-w-sm">
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            {t('ui.employees.vacation_balance.title')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-2xl font-semibold tabular-nums">
                            {t('ui.employees.vacation_balance.available', {
                                available: String(vacationBalance.available),
                            })}
                        </p>
                        <p className="text-xs text-muted-foreground tabular-nums">
                            {t('ui.employees.vacation_balance.summary', {
                                used: String(vacationBalance.used),
                                total: String(vacationBalance.total),
                            })}
                        </p>
                    </CardContent>
                </Card>

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
                    data={leaves}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    extraParams={extraParams}
                    only={['leaves', 'filters']}
                    emptyLabel={t('ui.leaves.my.empty')}
                    toolbar={
                        <div className="flex flex-wrap items-center gap-2">
                            <Input
                                type="date"
                                value={from}
                                onChange={(event) =>
                                    setFrom(event.target.value)
                                }
                                aria-label={t('ui.leaves.filters.from')}
                                className="w-[150px]"
                            />
                            <Input
                                type="date"
                                value={to}
                                onChange={(event) => setTo(event.target.value)}
                                aria-label={t('ui.leaves.filters.to')}
                                className="w-[150px]"
                            />
                        </div>
                    }
                />
            </div>

            <Dialog
                open={viewTarget !== null}
                onOpenChange={(open) => !open && setViewTarget(null)}
            >
                <DialogContent className="max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{t('ui.leaves.detail.title')}</DialogTitle>
                        <DialogDescription>
                            {viewTarget?.type_label ??
                                t('ui.leaves.detail.none')}
                        </DialogDescription>
                    </DialogHeader>

                    {viewTarget && (
                        <div className="flex flex-col gap-4 py-2">
                            <dl className="grid gap-3">
                                <DetailRow
                                    label={t('ui.leaves.detail.type')}
                                    value={
                                        <Badge variant="outline">
                                            {viewTarget.type_label}
                                        </Badge>
                                    }
                                />
                                <DetailRow
                                    label={t('ui.leaves.detail.status')}
                                    value={
                                        <Badge
                                            variant={
                                                STATUS_VARIANT[
                                                    viewTarget.status
                                                ] ?? 'outline'
                                            }
                                        >
                                            {viewTarget.status_label}
                                        </Badge>
                                    }
                                />
                                <DetailRow
                                    label={t('ui.leaves.detail.start_date')}
                                    value={viewTarget.start_date}
                                />
                                <DetailRow
                                    label={t('ui.leaves.detail.end_date')}
                                    value={viewTarget.end_date}
                                />
                                {viewTarget.half_day &&
                                    viewTarget.half_day_type && (
                                        <DetailRow
                                            label={t(
                                                'ui.leaves.detail.half_day',
                                            )}
                                            value={t(
                                                `ui.leaves.half_day_types.${viewTarget.half_day_type}`,
                                            )}
                                        />
                                    )}
                                <DetailRow
                                    label={t('ui.leaves.detail.days')}
                                    value={viewTarget.business_days_requested}
                                />
                                <DetailRow
                                    label={t('ui.leaves.detail.approved_by')}
                                    value={
                                        viewTarget.approved_by ??
                                        t('ui.leaves.detail.none')
                                    }
                                />
                                <DetailRow
                                    label={t('ui.leaves.detail.created_at')}
                                    value={
                                        viewTarget.created_at ??
                                        t('ui.leaves.detail.none')
                                    }
                                />
                            </dl>

                            {viewTarget.is_medical && (
                                <>
                                    <Separator />
                                    <div className="grid gap-3">
                                        <h3 className="text-sm font-medium">
                                            {t('ui.leaves.detail.medical')}
                                        </h3>
                                        <DetailRow
                                            label={t(
                                                'ui.leaves.detail.medical_leave_number',
                                            )}
                                            value={
                                                viewTarget.medical_leave_number ??
                                                t('ui.leaves.detail.none')
                                            }
                                        />
                                        <DetailRow
                                            label={t(
                                                'ui.leaves.detail.medical_leave_doctor',
                                            )}
                                            value={
                                                viewTarget.medical_leave_doctor ??
                                                t('ui.leaves.detail.none')
                                            }
                                        />
                                    </div>
                                </>
                            )}

                            <Separator />
                            <div className="grid gap-2">
                                <h3 className="text-sm font-medium">
                                    {t('ui.leaves.detail.notes')}
                                </h3>
                                <p className="text-sm whitespace-pre-line text-muted-foreground">
                                    {viewTarget.notes ||
                                        t('ui.leaves.detail.no_notes')}
                                </p>
                            </div>
                        </div>
                    )}

                    <DialogFooter>
                        {viewTarget && viewTarget.status === 'pending' && (
                            <Button
                                variant="outline"
                                className="text-destructive hover:text-destructive"
                                onClick={() => {
                                    setCancelTarget(viewTarget);
                                    setViewTarget(null);
                                }}
                            >
                                <X className="size-4" />
                                {t('ui.leaves.actions.cancel')}
                            </Button>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={cancelTarget !== null}
                onOpenChange={(open) => !open && setCancelTarget(null)}
                title={t('ui.leaves.my.cancel_dialog.title')}
                description={t('ui.leaves.my.cancel_dialog.description')}
                confirmLabel={t('ui.leaves.actions.cancel')}
                onConfirm={confirmCancel}
            />
        </>
    );
}
