import { Head, Link, useForm } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { ArrowLeft, Check, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { DataTable } from '@/components/data-table';
import { FormField } from '@/components/form-field';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslations } from '@/hooks/use-translations';
import { toneChip } from '@/lib/status-tone';
import { cn } from '@/lib/utils';
import { index as overtimeIndex } from '@/routes/overtime';
import { approve, index, reject } from '@/routes/overtime/requests';
import type { Paginated } from '@/types/ui';

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
    requests: Paginated<OvertimeRequestRow>;
    filters: {
        status: string | null;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
    statusOptions: Option[];
    can: {
        decide: boolean;
    };
};

/** Drop the seconds from a stored `HH:MM:SS` figure for compact display. */
function hm(time: string): string {
    return time.slice(0, 5);
}

export default function OvertimeRequestsIndex({
    requests,
    filters,
    statusOptions,
    can,
}: Props) {
    const { t } = useTranslations();

    const [status, setStatus] = useState(filters.status ?? 'pending');

    const [approveTarget, setApproveTarget] =
        useState<OvertimeRequestRow | null>(null);
    const [rejectTarget, setRejectTarget] = useState<OvertimeRequestRow | null>(
        null,
    );

    const approveForm = useForm({});
    const rejectForm = useForm({ reason: '' });

    const extraParams = useMemo(() => ({ status }), [status]);

    const statusTabs = useMemo(
        () => [
            ...statusOptions,
            { value: 'all', label: t('ui.overtime.requests.review.tabs.all') },
        ],
        [statusOptions, t],
    );

    function submitApprove() {
        if (!approveTarget) {
            return;
        }

        approveForm.post(approve(approveTarget.id).url, {
            preserveScroll: true,
            onSuccess: () => setApproveTarget(null),
        });
    }

    function openReject(row: OvertimeRequestRow) {
        rejectForm.clearErrors();
        rejectForm.setData('reason', '');
        setRejectTarget(row);
    }

    function submitReject() {
        if (!rejectTarget) {
            return;
        }

        rejectForm.post(reject(rejectTarget.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                rejectForm.reset();
                setRejectTarget(null);
            },
        });
    }

    const columns = useMemo<ColumnDef<OvertimeRequestRow>[]>(
        () => [
            {
                id: 'employee',
                enableSorting: false,
                meta: {
                    title: t('ui.overtime.requests.review.columns.employee'),
                },
                header: () => t('ui.overtime.requests.review.columns.employee'),
                cell: ({ row }) => row.original.employee ?? '—',
            },
            {
                id: 'date',
                enableSorting: false,
                meta: { title: t('ui.overtime.requests.review.columns.date') },
                header: () => t('ui.overtime.requests.review.columns.date'),
                cell: ({ row }) => row.original.date,
            },
            {
                id: 'requested_hours',
                enableSorting: false,
                meta: {
                    title: t(
                        'ui.overtime.requests.review.columns.requested_hours',
                    ),
                    cellClassName: 'tabular-nums',
                },
                header: () =>
                    t('ui.overtime.requests.review.columns.requested_hours'),
                cell: ({ row }) => hm(row.original.requested_hours),
            },
            {
                id: 'reason',
                enableSorting: false,
                meta: {
                    title: t('ui.overtime.requests.review.columns.reason'),
                },
                header: () => t('ui.overtime.requests.review.columns.reason'),
                cell: ({ row }) => row.original.reason ?? '—',
            },
            {
                id: 'status',
                enableSorting: false,
                meta: {
                    title: t('ui.overtime.requests.review.columns.status'),
                },
                header: () => t('ui.overtime.requests.review.columns.status'),
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
                    title: t('ui.overtime.requests.review.columns.reviewed_by'),
                },
                header: () =>
                    t('ui.overtime.requests.review.columns.reviewed_by'),
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
                                onClick={() => setApproveTarget(row.original)}
                                aria-label={t(
                                    'ui.overtime.requests.review.actions.approve',
                                )}
                            >
                                <Check className="size-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="text-destructive hover:text-destructive"
                                onClick={() => openReject(row.original)}
                                aria-label={t(
                                    'ui.overtime.requests.review.actions.reject',
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
            <Head title={t('ui.overtime.requests.review.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={t('ui.overtime.requests.review.title')}
                        description={t(
                            'ui.overtime.requests.review.description',
                        )}
                    />
                    <Button variant="outline" asChild>
                        <Link href={overtimeIndex()}>
                            <ArrowLeft className="size-4" />
                            {t('ui.overtime.requests.review.back')}
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
                    emptyLabel={t('ui.overtime.requests.review.empty')}
                />
            </div>

            <Dialog
                open={approveTarget !== null}
                onOpenChange={(open) => !open && setApproveTarget(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t(
                                'ui.overtime.requests.review.approve_dialog.title',
                            )}
                        </DialogTitle>
                        <DialogDescription>
                            {t(
                                'ui.overtime.requests.review.approve_dialog.description',
                                {
                                    employee: approveTarget?.employee ?? '',
                                    date: approveTarget?.date ?? '',
                                },
                            )}
                        </DialogDescription>
                    </DialogHeader>

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
                            {t(
                                'ui.overtime.requests.review.approve_dialog.submit',
                            )}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={rejectTarget !== null}
                onOpenChange={(open) => !open && setRejectTarget(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t(
                                'ui.overtime.requests.review.reject_dialog.title',
                            )}
                        </DialogTitle>
                        <DialogDescription>
                            {t(
                                'ui.overtime.requests.review.reject_dialog.description',
                                {
                                    employee: rejectTarget?.employee ?? '',
                                    date: rejectTarget?.date ?? '',
                                },
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-2">
                        <FormField
                            label={t(
                                'ui.overtime.requests.review.reject_dialog.reason',
                            )}
                            htmlFor="reject_reason"
                            required
                            error={rejectForm.errors.reason}
                        >
                            <textarea
                                id="reject_reason"
                                rows={3}
                                value={rejectForm.data.reason}
                                onChange={(event) =>
                                    rejectForm.setData(
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
                            onClick={() => setRejectTarget(null)}
                        >
                            {t('ui.common.cancel')}
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={submitReject}
                            disabled={rejectForm.processing}
                        >
                            {t(
                                'ui.overtime.requests.review.reject_dialog.submit',
                            )}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
