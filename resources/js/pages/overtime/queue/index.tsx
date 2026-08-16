import { Head, Link, useForm } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { AlertTriangle, ArrowLeft, Check, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import { DataTableFacetedFilter } from '@/components/data-table-faceted-filter';
import type { FacetedOption } from '@/components/data-table-faceted-filter';
import { FormField } from '@/components/form-field';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/hooks/use-translations';
import {
    decimalHoursToTime,
    timeToDecimalHours,
} from '@/lib/overtime-duration';
import { toneChip } from '@/lib/status-tone';
import { cn } from '@/lib/utils';
import { index as overtimeIndex } from '@/routes/overtime';
import { approve, bulkDecide, index, object } from '@/routes/overtime/queue';
import {
    approve as approveRequest,
    reject as rejectRequest,
} from '@/routes/overtime/queue/requests';
import type { Paginated } from '@/types/ui';

type OvertimeAuthorizationRow = {
    id: number;
    employee: string | null;
    date: string;
    calculated_hours: string | null;
    requested_hours: string | null;
    authorized_hours: string | null;
    final_hours: string | null;
    status: string;
    status_label: string;
    status_badge: string;
    reason: string | null;
    reviewed_by: string | null;
    reviewed_at: string | null;
    is_flagged: boolean;
    anomaly_reasons: string[];
};

type OvertimeRequestRow = {
    id: number;
    employee: string | null;
    date: string;
    requested_hours: string;
    reason: string | null;
    status: string;
    status_label: string;
    status_badge: string;
    reviewed_by: string | null;
};

type Option = { value: string; label: string };

type Props = {
    authorizations: Paginated<OvertimeAuthorizationRow>;
    filters: {
        status: string | null;
        employees: string[];
        from: string | null;
        to: string | null;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
        request_status: string | null;
    };
    employeeOptions: FacetedOption[];
    statusOptions: Option[];
    requestStatusOptions: Option[];
    requests: Paginated<OvertimeRequestRow>;
    pendingRequestsCount: number;
    can: {
        decide: boolean;
        requests: boolean;
    };
};

/** Drop the seconds from a stored `HH:MM:SS` figure for compact display. */
function hm(time: string | null): string {
    return time ? time.slice(0, 5) : '—';
}

export default function OvertimeQueueIndex({
    authorizations,
    filters,
    employeeOptions,
    statusOptions,
    requestStatusOptions,
    requests,
    pendingRequestsCount,
    can,
}: Props) {
    const { t } = useTranslations();

    const [view, setView] = useState<'excess' | 'requests'>(
        can.requests ? 'requests' : 'excess',
    );

    const [status, setStatus] = useState(filters.status ?? 'pending');
    const [employees, setEmployees] = useState<string[]>(
        filters.employees ?? [],
    );
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
    const [requestStatus, setRequestStatus] = useState(
        filters.request_status ?? 'pending',
    );

    const [approveTarget, setApproveTarget] =
        useState<OvertimeAuthorizationRow | null>(null);
    const [objectTarget, setObjectTarget] =
        useState<OvertimeAuthorizationRow | null>(null);
    const [bulkAction, setBulkAction] = useState<'approve' | 'object' | null>(
        null,
    );
    const [bulkTargets, setBulkTargets] = useState<OvertimeAuthorizationRow[]>(
        [],
    );
    const [resetSelection, setResetSelection] = useState<() => void>(
        () => () => {},
    );

    const [approveRequestTarget, setApproveRequestTarget] =
        useState<OvertimeRequestRow | null>(null);
    const [rejectRequestTarget, setRejectRequestTarget] =
        useState<OvertimeRequestRow | null>(null);

    const approveForm = useForm({ authorized_hours: '', reason: '' });
    const objectForm = useForm({ reason: '' });
    const bulkForm = useForm({
        ids: [] as number[],
        action: 'approve' as 'approve' | 'object',
        reason: '',
    });
    const approveRequestForm = useForm({});
    const rejectRequestForm = useForm({ reason: '' });

    const extraParams = useMemo(
        () => ({
            // Sent as the literal "all" (not `undefined`) — an omitted param
            // reads server-side as "no filter chosen yet", which defaults
            // back to Pendiente rather than clearing the filter.
            status,
            employees: employees.length > 0 ? employees : undefined,
            from: from || undefined,
            to: to || undefined,
        }),
        [status, employees, from, to],
    );

    const statusTabs = useMemo(
        () => [
            ...statusOptions,
            { value: 'all', label: t('ui.overtime.queue.tabs.all') },
        ],
        [statusOptions, t],
    );

    const requestExtraParams = useMemo(
        () => ({ request_status: requestStatus }),
        [requestStatus],
    );

    const requestStatusTabs = useMemo(
        () => [
            ...requestStatusOptions,
            { value: 'all', label: t('ui.overtime.queue.tabs.all') },
        ],
        [requestStatusOptions, t],
    );

    function openApprove(row: OvertimeAuthorizationRow) {
        approveForm.clearErrors();
        approveForm.setData({
            authorized_hours: timeToDecimalHours(
                row.authorized_hours ?? row.calculated_hours,
            ),
            reason: '',
        });
        setApproveTarget(row);
    }

    function submitApprove() {
        if (!approveTarget) {
            return;
        }

        approveForm.transform((data) => ({
            ...data,
            authorized_hours: decimalHoursToTime(data.authorized_hours),
        }));
        approveForm.post(approve(approveTarget.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                approveForm.reset();
                setApproveTarget(null);
            },
        });
    }

    function openObject(row: OvertimeAuthorizationRow) {
        objectForm.clearErrors();
        objectForm.setData('reason', '');
        setObjectTarget(row);
    }

    function submitObject() {
        if (!objectTarget) {
            return;
        }

        objectForm.post(object(objectTarget.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                objectForm.reset();
                setObjectTarget(null);
            },
        });
    }

    function submitApproveRequest() {
        if (!approveRequestTarget) {
            return;
        }

        approveRequestForm.post(approveRequest(approveRequestTarget.id).url, {
            preserveScroll: true,
            onSuccess: () => setApproveRequestTarget(null),
        });
    }

    function openRejectRequest(row: OvertimeRequestRow) {
        rejectRequestForm.clearErrors();
        rejectRequestForm.setData('reason', '');
        setRejectRequestTarget(row);
    }

    function submitRejectRequest() {
        if (!rejectRequestTarget) {
            return;
        }

        rejectRequestForm.post(rejectRequest(rejectRequestTarget.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                rejectRequestForm.reset();
                setRejectRequestTarget(null);
            },
        });
    }

    function openBulk(
        action: 'approve' | 'object',
        rows: OvertimeAuthorizationRow[],
        reset: () => void,
    ) {
        setBulkAction(action);
        setBulkTargets(rows);
        setResetSelection(() => reset);
        bulkForm.clearErrors();
        bulkForm.setData({
            ids: rows.map((row) => row.id),
            action,
            reason: '',
        });
    }

    function submitBulk() {
        bulkForm.post(bulkDecide().url, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                bulkForm.reset('reason');
                resetSelection();
                setBulkTargets([]);
                setBulkAction(null);
            },
        });
    }

    const columns = useMemo<ColumnDef<OvertimeAuthorizationRow>[]>(() => {
        const cols: ColumnDef<OvertimeAuthorizationRow>[] = [];

        if (can.decide) {
            cols.push({
                id: 'select',
                enableSorting: false,
                enableHiding: false,
                meta: { headClassName: 'w-8', cellClassName: 'w-8' },
                header: ({ table }) => (
                    <Checkbox
                        checked={
                            table.getIsAllPageRowsSelected() ||
                            (table.getIsSomePageRowsSelected() &&
                                'indeterminate')
                        }
                        onCheckedChange={(value) =>
                            table.toggleAllPageRowsSelected(!!value)
                        }
                        aria-label={t('ui.overtime.queue.select_all')}
                    />
                ),
                cell: ({ row }) =>
                    row.original.status === 'pending' ? (
                        <Checkbox
                            checked={row.getIsSelected()}
                            onCheckedChange={(value) =>
                                row.toggleSelected(!!value)
                            }
                            aria-label={t('ui.overtime.queue.select_row')}
                        />
                    ) : null,
            });
        }

        cols.push(
            {
                id: 'employee',
                enableSorting: false,
                meta: { title: t('ui.overtime.queue.columns.employee') },
                header: () => t('ui.overtime.queue.columns.employee'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-2">
                        <span className="font-medium">
                            {row.original.employee ?? '—'}
                        </span>
                        {row.original.is_flagged && (
                            <Badge
                                variant="outline"
                                className="gap-1 border-amber-500/50 text-amber-600 dark:text-amber-400"
                                title={t('ui.overtime.queue.flags.tooltip', {
                                    reasons:
                                        row.original.anomaly_reasons.join(', '),
                                })}
                            >
                                <AlertTriangle className="size-3" />
                                {t('ui.overtime.queue.flags.label')}
                            </Badge>
                        )}
                    </div>
                ),
            },
            {
                accessorKey: 'date',
                meta: { title: t('ui.overtime.queue.columns.date') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.overtime.queue.columns.date')}
                    />
                ),
                cell: ({ row }) => row.original.date,
            },
            {
                id: 'calculated_hours',
                meta: {
                    title: t('ui.overtime.queue.columns.calculated_hours'),
                    cellClassName: 'tabular-nums',
                },
                header: () => t('ui.overtime.queue.columns.calculated_hours'),
                cell: ({ row }) => hm(row.original.calculated_hours),
            },
            {
                id: 'authorized_hours',
                meta: {
                    title: t('ui.overtime.queue.columns.authorized_hours'),
                    cellClassName: 'tabular-nums',
                },
                header: () => t('ui.overtime.queue.columns.authorized_hours'),
                cell: ({ row }) => hm(row.original.authorized_hours),
            },
            {
                id: 'status',
                enableSorting: false,
                meta: { title: t('ui.overtime.queue.columns.status') },
                header: () => t('ui.overtime.queue.columns.status'),
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
                meta: { title: t('ui.overtime.queue.columns.reviewed_by') },
                header: () => t('ui.overtime.queue.columns.reviewed_by'),
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
                cell: ({ row }) =>
                    can.decide && row.original.status === 'pending' ? (
                        <div className="flex items-center justify-end gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="text-green-600 hover:text-green-700 dark:text-green-500"
                                onClick={() => openApprove(row.original)}
                                aria-label={t(
                                    'ui.overtime.queue.actions.approve',
                                )}
                            >
                                <Check className="size-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="text-destructive hover:text-destructive"
                                onClick={() => openObject(row.original)}
                                aria-label={t(
                                    'ui.overtime.queue.actions.object',
                                )}
                            >
                                <X className="size-4" />
                            </Button>
                        </div>
                    ) : null,
            },
        );

        return cols;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [t, can.decide]);

    const requestColumns = useMemo<ColumnDef<OvertimeRequestRow>[]>(
        () => [
            {
                id: 'employee',
                enableSorting: false,
                meta: {
                    title: t('ui.overtime.queue.requests.columns.employee'),
                },
                header: () => t('ui.overtime.queue.requests.columns.employee'),
                cell: ({ row }) => row.original.employee ?? '—',
            },
            {
                id: 'date',
                enableSorting: false,
                meta: { title: t('ui.overtime.queue.requests.columns.date') },
                header: () => t('ui.overtime.queue.requests.columns.date'),
                cell: ({ row }) => row.original.date,
            },
            {
                id: 'requested_hours',
                enableSorting: false,
                meta: {
                    title: t(
                        'ui.overtime.queue.requests.columns.requested_hours',
                    ),
                    cellClassName: 'tabular-nums',
                },
                header: () =>
                    t('ui.overtime.queue.requests.columns.requested_hours'),
                cell: ({ row }) => hm(row.original.requested_hours),
            },
            {
                id: 'reason',
                enableSorting: false,
                meta: { title: t('ui.overtime.queue.requests.columns.reason') },
                header: () => t('ui.overtime.queue.requests.columns.reason'),
                cell: ({ row }) => row.original.reason ?? '—',
            },
            {
                id: 'status',
                enableSorting: false,
                meta: { title: t('ui.overtime.queue.requests.columns.status') },
                header: () => t('ui.overtime.queue.requests.columns.status'),
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
                    title: t('ui.overtime.queue.requests.columns.reviewed_by'),
                },
                header: () =>
                    t('ui.overtime.queue.requests.columns.reviewed_by'),
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
                cell: ({ row }) =>
                    can.decide && row.original.status === 'pending' ? (
                        <div className="flex items-center justify-end gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="text-green-600 hover:text-green-700 dark:text-green-500"
                                onClick={() =>
                                    setApproveRequestTarget(row.original)
                                }
                                aria-label={t(
                                    'ui.overtime.queue.requests.actions.approve',
                                )}
                            >
                                <Check className="size-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="text-destructive hover:text-destructive"
                                onClick={() => openRejectRequest(row.original)}
                                aria-label={t(
                                    'ui.overtime.queue.requests.actions.reject',
                                )}
                            >
                                <X className="size-4" />
                            </Button>
                        </div>
                    ) : null,
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [t, can.decide],
    );

    return (
        <>
            <Head title={t('ui.overtime.queue.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={t('ui.overtime.queue.title')}
                        description={t('ui.overtime.queue.description')}
                    />
                    <Button variant="outline" asChild>
                        <Link href={overtimeIndex()}>
                            <ArrowLeft className="size-4" />
                            {t('ui.overtime.queue.back')}
                        </Link>
                    </Button>
                </div>

                {can.requests && (
                    <div className="flex flex-wrap items-center gap-1 rounded-lg bg-muted p-1">
                        <button
                            type="button"
                            onClick={() => setView('excess')}
                            className={cn(
                                'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                view === 'excess'
                                    ? 'bg-background text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {t('ui.overtime.queue.tabs.excess')}
                        </button>
                        <button
                            type="button"
                            onClick={() => setView('requests')}
                            className={cn(
                                'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                view === 'requests'
                                    ? 'bg-background text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {t('ui.overtime.queue.tabs.requests')}
                            {pendingRequestsCount > 0 && (
                                <span className="ml-1.5 rounded-full bg-primary/10 px-1.5 py-0.5 text-xs text-primary">
                                    {pendingRequestsCount}
                                </span>
                            )}
                        </button>
                    </div>
                )}

                {view === 'requests' ? (
                    <>
                        <div className="flex flex-wrap items-center gap-1 rounded-lg bg-muted p-1">
                            {requestStatusTabs.map((tab) => (
                                <button
                                    key={tab.value}
                                    type="button"
                                    onClick={() => setRequestStatus(tab.value)}
                                    className={cn(
                                        'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                        requestStatus === tab.value
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
                            columns={requestColumns}
                            routeUrl={index().url}
                            extraParams={requestExtraParams}
                            only={[
                                'requests',
                                'filters',
                                'pendingRequestsCount',
                            ]}
                            emptyLabel={t('ui.overtime.queue.requests.empty')}
                        />
                    </>
                ) : (
                    <>
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
                            data={authorizations}
                            columns={columns}
                            routeUrl={index().url}
                            filters={filters}
                            extraParams={extraParams}
                            only={['authorizations', 'filters']}
                            emptyLabel={t('ui.overtime.queue.empty')}
                            enableRowSelection={can.decide}
                            getRowId={(row) => String(row.id)}
                            renderSelectionActions={
                                can.decide
                                    ? (rows, reset) => (
                                          <>
                                              <span className="text-sm font-medium">
                                                  {t(
                                                      'ui.overtime.queue.selected',
                                                      {
                                                          count: rows.length,
                                                      },
                                                  )}
                                              </span>
                                              <Button
                                                  size="sm"
                                                  onClick={() =>
                                                      openBulk(
                                                          'approve',
                                                          rows,
                                                          reset,
                                                      )
                                                  }
                                              >
                                                  {t(
                                                      'ui.overtime.queue.bulk.trigger_approve',
                                                  )}
                                              </Button>
                                              <Button
                                                  size="sm"
                                                  variant="outline"
                                                  className="text-destructive hover:text-destructive"
                                                  onClick={() =>
                                                      openBulk(
                                                          'object',
                                                          rows,
                                                          reset,
                                                      )
                                                  }
                                              >
                                                  {t(
                                                      'ui.overtime.queue.bulk.trigger_object',
                                                  )}
                                              </Button>
                                          </>
                                      )
                                    : undefined
                            }
                            toolbar={
                                <div className="flex flex-wrap items-center gap-2">
                                    <DataTableFacetedFilter
                                        title={t(
                                            'ui.overtime.queue.filters.employee',
                                        )}
                                        options={employeeOptions}
                                        selected={employees}
                                        onChange={setEmployees}
                                    />
                                    <Input
                                        type="date"
                                        value={from}
                                        onChange={(event) =>
                                            setFrom(event.target.value)
                                        }
                                        aria-label={t(
                                            'ui.overtime.queue.filters.from',
                                        )}
                                        className="w-[150px]"
                                    />
                                    <Input
                                        type="date"
                                        value={to}
                                        onChange={(event) =>
                                            setTo(event.target.value)
                                        }
                                        aria-label={t(
                                            'ui.overtime.queue.filters.to',
                                        )}
                                        className="w-[150px]"
                                    />
                                </div>
                            }
                        />
                    </>
                )}
            </div>

            <Dialog
                open={approveTarget !== null}
                onOpenChange={(open) => !open && setApproveTarget(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('ui.overtime.queue.approve_dialog.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('ui.overtime.queue.approve_dialog.description', {
                                employee: approveTarget?.employee ?? '',
                                date: approveTarget?.date ?? '',
                            })}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-2">
                        <FormField
                            label={t(
                                'ui.overtime.queue.approve_dialog.authorized_hours',
                            )}
                            htmlFor="approve_authorized_hours"
                            error={approveForm.errors.authorized_hours}
                        >
                            <Input
                                id="approve_authorized_hours"
                                type="number"
                                min="0"
                                step="0.25"
                                className="w-28"
                                value={approveForm.data.authorized_hours}
                                onChange={(event) =>
                                    approveForm.setData(
                                        'authorized_hours',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>

                        <FormField
                            label={t('ui.overtime.queue.approve_dialog.reason')}
                            htmlFor="approve_reason"
                            hint={t(
                                'ui.overtime.queue.approve_dialog.reason_hint',
                            )}
                            error={approveForm.errors.reason}
                        >
                            <textarea
                                id="approve_reason"
                                rows={3}
                                value={approveForm.data.reason}
                                onChange={(event) =>
                                    approveForm.setData(
                                        'reason',
                                        event.target.value,
                                    )
                                }
                                className="flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            />
                        </FormField>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setApproveTarget(null)}
                        >
                            {t('ui.common.cancel')}
                        </Button>
                        <Button
                            onClick={submitApprove}
                            disabled={approveForm.processing}
                        >
                            {t('ui.overtime.queue.approve_dialog.submit')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={objectTarget !== null}
                onOpenChange={(open) => !open && setObjectTarget(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('ui.overtime.queue.object_dialog.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('ui.overtime.queue.object_dialog.description', {
                                employee: objectTarget?.employee ?? '',
                                date: objectTarget?.date ?? '',
                            })}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-2">
                        <FormField
                            label={t('ui.overtime.queue.object_dialog.reason')}
                            htmlFor="object_reason"
                            required
                            error={objectForm.errors.reason}
                        >
                            <textarea
                                id="object_reason"
                                rows={3}
                                value={objectForm.data.reason}
                                onChange={(event) =>
                                    objectForm.setData(
                                        'reason',
                                        event.target.value,
                                    )
                                }
                                className="flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            />
                        </FormField>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setObjectTarget(null)}
                        >
                            {t('ui.common.cancel')}
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={submitObject}
                            disabled={objectForm.processing}
                        >
                            {t('ui.overtime.queue.object_dialog.submit')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={bulkAction !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setBulkAction(null);
                        setBulkTargets([]);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {bulkAction === 'object'
                                ? t('ui.overtime.queue.bulk.object_title')
                                : t('ui.overtime.queue.bulk.approve_title')}
                        </DialogTitle>
                        <DialogDescription>
                            {bulkAction === 'object'
                                ? t(
                                      'ui.overtime.queue.bulk.object_description',
                                      { count: bulkTargets.length },
                                  )
                                : t(
                                      'ui.overtime.queue.bulk.approve_description',
                                      { count: bulkTargets.length },
                                  )}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-2">
                        <FormField
                            label={t('ui.overtime.queue.bulk.reason')}
                            htmlFor="bulk_reason"
                            required={bulkAction === 'object'}
                            error={bulkForm.errors.reason}
                        >
                            <textarea
                                id="bulk_reason"
                                rows={3}
                                value={bulkForm.data.reason}
                                onChange={(event) =>
                                    bulkForm.setData(
                                        'reason',
                                        event.target.value,
                                    )
                                }
                                className="flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            />
                        </FormField>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setBulkAction(null);
                                setBulkTargets([]);
                            }}
                        >
                            {t('ui.common.cancel')}
                        </Button>
                        <Button
                            variant={
                                bulkAction === 'object'
                                    ? 'destructive'
                                    : 'default'
                            }
                            onClick={submitBulk}
                            disabled={bulkForm.processing}
                        >
                            {t('ui.overtime.queue.bulk.submit')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={approveRequestTarget !== null}
                onOpenChange={(open) => !open && setApproveRequestTarget(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t(
                                'ui.overtime.queue.requests.approve_dialog.title',
                            )}
                        </DialogTitle>
                        <DialogDescription>
                            {t(
                                'ui.overtime.queue.requests.approve_dialog.description',
                                {
                                    employee:
                                        approveRequestTarget?.employee ?? '',
                                    date: approveRequestTarget?.date ?? '',
                                },
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setApproveRequestTarget(null)}
                        >
                            {t('ui.common.cancel')}
                        </Button>
                        <Button
                            onClick={submitApproveRequest}
                            disabled={approveRequestForm.processing}
                        >
                            {t(
                                'ui.overtime.queue.requests.approve_dialog.submit',
                            )}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={rejectRequestTarget !== null}
                onOpenChange={(open) => !open && setRejectRequestTarget(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t(
                                'ui.overtime.queue.requests.reject_dialog.title',
                            )}
                        </DialogTitle>
                        <DialogDescription>
                            {t(
                                'ui.overtime.queue.requests.reject_dialog.description',
                                {
                                    employee:
                                        rejectRequestTarget?.employee ?? '',
                                    date: rejectRequestTarget?.date ?? '',
                                },
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-2">
                        <FormField
                            label={t(
                                'ui.overtime.queue.requests.reject_dialog.reason',
                            )}
                            htmlFor="reject_request_reason"
                            required
                            error={rejectRequestForm.errors.reason}
                        >
                            <textarea
                                id="reject_request_reason"
                                rows={3}
                                value={rejectRequestForm.data.reason}
                                onChange={(event) =>
                                    rejectRequestForm.setData(
                                        'reason',
                                        event.target.value,
                                    )
                                }
                                className="flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            />
                        </FormField>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setRejectRequestTarget(null)}
                        >
                            {t('ui.common.cancel')}
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={submitRejectRequest}
                            disabled={rejectRequestForm.processing}
                        >
                            {t(
                                'ui.overtime.queue.requests.reject_dialog.submit',
                            )}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
