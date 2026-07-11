import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Check, Plus, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import { DataTableFacetedFilter } from '@/components/data-table-faceted-filter';
import type { FacetedOption } from '@/components/data-table-faceted-filter';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { approve, create, index, reject } from '@/routes/leaves';
import type { Paginated } from '@/types/ui';

type Leave = {
    id: number;
    employee: string | null;
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
};

type Option = { value: string; label: string };

type Props = {
    leaves: Paginated<Leave>;
    filters: {
        status: string | null;
        employees: string[];
        from: string | null;
        to: string | null;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
    employeeOptions: FacetedOption[];
    statusOptions: Option[];
};

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'destructive'> = {
    pending: 'secondary',
    approved: 'default',
    rejected: 'destructive',
};

export default function LeavesIndex({
    leaves,
    filters,
    employeeOptions,
    statusOptions,
}: Props) {
    const { t } = useTranslations();

    const [status, setStatus] = useState(filters.status ?? 'all');
    const [employees, setEmployees] = useState<string[]>(
        filters.employees ?? [],
    );
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    const [approveTarget, setApproveTarget] = useState<Leave | null>(null);
    const [rejectTarget, setRejectTarget] = useState<Leave | null>(null);

    const extraParams = useMemo(
        () => ({
            status: status === 'all' ? undefined : status,
            employees: employees.length > 0 ? employees : undefined,
            from: from || undefined,
            to: to || undefined,
        }),
        [status, employees, from, to],
    );

    const statusTabs = useMemo(
        () => [{ value: 'all', label: t('ui.leaves.tabs.all') }, ...statusOptions],
        [statusOptions, t],
    );

    function confirmApprove() {
        if (!approveTarget) {
            return;
        }

        router.post(
            approve(approveTarget.id).url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setApproveTarget(null),
            },
        );
    }

    function confirmReject() {
        if (!rejectTarget) {
            return;
        }

        router.post(
            reject(rejectTarget.id).url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setRejectTarget(null),
            },
        );
    }

    const columns = useMemo<ColumnDef<Leave>[]>(
        () => [
            {
                id: 'employee',
                enableSorting: false,
                meta: { title: t('ui.leaves.columns.employee') },
                header: () => t('ui.leaves.columns.employee'),
                cell: ({ row }) => (
                    <span className="font-medium">
                        {row.original.employee ?? '—'}
                    </span>
                ),
            },
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
                        variant={STATUS_VARIANT[row.original.status] ?? 'outline'}
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
                        {row.original.status !== 'approved' && (
                            <Button
                                variant="ghost"
                                size="icon"
                                className="text-green-600 hover:text-green-700 dark:text-green-500"
                                onClick={() => setApproveTarget(row.original)}
                                aria-label={t('ui.leaves.actions.approve')}
                            >
                                <Check className="size-4" />
                            </Button>
                        )}
                        {row.original.status !== 'rejected' &&
                            !row.original.is_medical && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="text-destructive hover:text-destructive"
                                    onClick={() => setRejectTarget(row.original)}
                                    aria-label={t('ui.leaves.actions.reject')}
                                >
                                    <X className="size-4" />
                                </Button>
                            )}
                    </div>
                ),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('ui.leaves.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={t('ui.leaves.title')}
                        description={t('ui.leaves.description')}
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="size-4" />
                            {t('ui.leaves.new')}
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
                    data={leaves}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    extraParams={extraParams}
                    only={['leaves', 'filters']}
                    emptyLabel={t('ui.leaves.empty')}
                    toolbar={
                        <div className="flex flex-wrap items-center gap-2">
                            <DataTableFacetedFilter
                                title={t('ui.leaves.filters.employee')}
                                options={employeeOptions}
                                selected={employees}
                                onChange={setEmployees}
                            />
                            <Input
                                type="date"
                                value={from}
                                onChange={(event) => setFrom(event.target.value)}
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

            <ConfirmDialog
                open={approveTarget !== null}
                onOpenChange={(open) => !open && setApproveTarget(null)}
                title={t('ui.leaves.approve_dialog.title')}
                description={t('ui.leaves.approve_dialog.description', {
                    name: approveTarget?.employee ?? '',
                })}
                confirmLabel={t('ui.leaves.actions.approve')}
                onConfirm={confirmApprove}
            />

            <ConfirmDialog
                open={rejectTarget !== null}
                onOpenChange={(open) => !open && setRejectTarget(null)}
                title={t('ui.leaves.reject_dialog.title')}
                description={t('ui.leaves.reject_dialog.description', {
                    name: rejectTarget?.employee ?? '',
                })}
                confirmLabel={t('ui.leaves.actions.reject')}
                onConfirm={confirmReject}
            />
        </>
    );
}
