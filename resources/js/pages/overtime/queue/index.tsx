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
import { cn } from '@/lib/utils';
import { index as overtimeIndex } from '@/routes/overtime';
import { approve, bulkDecide, index, object } from '@/routes/overtime/queue';
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
    };
    employeeOptions: FacetedOption[];
    statusOptions: Option[];
    can: {
        decide: boolean;
    };
};

const STATUS_VARIANT: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    success: 'default',
    warning: 'secondary',
    destructive: 'destructive',
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
    can,
}: Props) {
    const { t } = useTranslations();

    const [status, setStatus] = useState(filters.status ?? 'pending');
    const [employees, setEmployees] = useState<string[]>(
        filters.employees ?? [],
    );
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

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

    const approveForm = useForm({ authorized_hours: '', reason: '' });
    const objectForm = useForm({ reason: '' });
    const bulkForm = useForm({
        ids: [] as number[],
        action: 'approve' as 'approve' | 'object',
        reason: '',
    });

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
        () => [
            ...statusOptions,
            { value: 'all', label: t('ui.overtime.queue.tabs.all') },
        ],
        [statusOptions, t],
    );

    function openApprove(row: OvertimeAuthorizationRow) {
        approveForm.clearErrors();
        approveForm.setData({
            authorized_hours: hm(row.authorized_hours ?? row.calculated_hours),
            reason: '',
        });
        setApproveTarget(row);
    }

    function submitApprove() {
        if (!approveTarget) {
            return;
        }

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
                                        row.original.anomaly_reasons.join(
                                            ', ',
                                        ),
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
                    <Badge
                        variant={
                            STATUS_VARIANT[row.original.status_badge] ??
                            'outline'
                        }
                    >
                        {row.original.status_label}
                    </Badge>
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
                                          {t('ui.overtime.queue.selected', {
                                              count: rows.length,
                                          })}
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
                                              openBulk('object', rows, reset)
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
                                title={t('ui.overtime.queue.filters.employee')}
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
                                aria-label={t('ui.overtime.queue.filters.from')}
                                className="w-[150px]"
                            />
                            <Input
                                type="date"
                                value={to}
                                onChange={(event) => setTo(event.target.value)}
                                aria-label={t('ui.overtime.queue.filters.to')}
                                className="w-[150px]"
                            />
                        </div>
                    }
                />
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
                                type="time"
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
        </>
    );
}
