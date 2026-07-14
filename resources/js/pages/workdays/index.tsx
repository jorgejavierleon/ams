import { Head, useForm } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { AlertTriangle, Eye } from 'lucide-react';
import type { ReactNode } from 'react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { bulkModify, index } from '@/routes/workdays';
import type { Paginated } from '@/types/ui';

type Workday = {
    id: number;
    employee: string | null;
    date: string;
    status: string | null;
    status_label: string | null;
    status_badge: string | null;
    mark_in_at: string | null;
    mark_out_at: string | null;
    worked_time: string | null;
    in_time_difference: string | null;
    out_time_difference: string | null;
    shift: string | null;
    leave_type: string | null;
    pending_modifications: number;
};

type Option = { value: string; label: string };

type Props = {
    workdays: Paginated<Workday>;
    filters: {
        from: string;
        to: string;
        statuses: string[];
        employees: string[];
        positions: string[];
        premises: string[];
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
    statusOptions: Option[];
    employeeOptions: FacetedOption[];
    positionOptions: FacetedOption[];
    premiseOptions: FacetedOption[];
    reasonOptions: Option[];
    markTypeOptions: Option[];
};

const STATUS_BADGE: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    success: 'default',
    warning: 'secondary',
    destructive: 'destructive',
};

/** Local-timezone `YYYY-MM-DD`, avoiding the UTC shift of `toISOString`. */
function toDateString(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function DetailRow({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-4">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-sm font-medium">{value}</dd>
        </div>
    );
}

export default function WorkdaysIndex({
    workdays,
    filters,
    statusOptions,
    employeeOptions,
    positionOptions,
    premiseOptions,
    reasonOptions,
    markTypeOptions,
}: Props) {
    const { t } = useTranslations();

    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [statuses, setStatuses] = useState<string[]>(filters.statuses ?? []);
    const [employees, setEmployees] = useState<string[]>(
        filters.employees ?? [],
    );
    const [positions, setPositions] = useState<string[]>(
        filters.positions ?? [],
    );
    const [premises, setPremises] = useState<string[]>(filters.premises ?? []);

    const [viewTarget, setViewTarget] = useState<Workday | null>(null);
    const [bulkTargets, setBulkTargets] = useState<Workday[]>([]);
    const [resetSelection, setResetSelection] = useState<() => void>(
        () => () => {},
    );

    const bulkForm = useForm({
        workdays: [] as number[],
        mark_type: markTypeOptions[0]?.value ?? 'in',
        time: '',
        reason: reasonOptions[0]?.value ?? '',
        notes: '',
    });

    const extraParams = useMemo(
        () => ({
            from: from || undefined,
            to: to || undefined,
            statuses: statuses.length > 0 ? statuses : undefined,
            employees: employees.length > 0 ? employees : undefined,
            positions: positions.length > 0 ? positions : undefined,
            premises: premises.length > 0 ? premises : undefined,
        }),
        [from, to, statuses, employees, positions, premises],
    );

    const quickRanges = useMemo(() => {
        const today = new Date();
        const yesterday = new Date(today);
        yesterday.setDate(today.getDate() - 1);

        const weekStart = new Date(today);
        // Week starts Monday; getDay() is 0=Sun … 6=Sat.
        const daysSinceMonday = (today.getDay() + 6) % 7;
        weekStart.setDate(today.getDate() - daysSinceMonday);

        const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);

        return [
            {
                key: 'today',
                label: t('ui.workdays.ranges.today'),
                from: toDateString(today),
                to: toDateString(today),
            },
            {
                key: 'yesterday',
                label: t('ui.workdays.ranges.yesterday'),
                from: toDateString(yesterday),
                to: toDateString(yesterday),
            },
            {
                key: 'week',
                label: t('ui.workdays.ranges.week'),
                from: toDateString(weekStart),
                to: toDateString(today),
            },
            {
                key: 'month',
                label: t('ui.workdays.ranges.month'),
                from: toDateString(monthStart),
                to: toDateString(today),
            },
        ];
    }, [t]);

    function applyRange(range: { from: string; to: string }) {
        setFrom(range.from);
        setTo(range.to);
    }

    function openBulk(rows: Workday[], reset: () => void) {
        setBulkTargets(rows);
        setResetSelection(() => reset);
        bulkForm.setData(
            'workdays',
            rows.map((row) => row.id),
        );
    }

    function submitBulk() {
        bulkForm.post(bulkModify().url, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                bulkForm.reset('notes', 'time');
                resetSelection();
                setBulkTargets([]);
            },
        });
    }

    const columns = useMemo<ColumnDef<Workday>[]>(
        () => [
            {
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
                        aria-label={t('ui.workdays.select_all')}
                    />
                ),
                cell: ({ row }) => (
                    <Checkbox
                        checked={row.getIsSelected()}
                        onCheckedChange={(value) => row.toggleSelected(!!value)}
                        aria-label={t('ui.workdays.select_row')}
                    />
                ),
            },
            {
                id: 'employee',
                enableSorting: false,
                meta: { title: t('ui.workdays.columns.employee') },
                header: () => t('ui.workdays.columns.employee'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-2">
                        <span className="font-medium">
                            {row.original.employee ?? '—'}
                        </span>
                        {row.original.pending_modifications > 0 && (
                            <Badge
                                variant="outline"
                                className="gap-1 border-amber-500/50 text-amber-600 dark:text-amber-400"
                                title={t('ui.workdays.pending_hint')}
                            >
                                <AlertTriangle className="size-3" />
                                {row.original.pending_modifications}
                            </Badge>
                        )}
                    </div>
                ),
            },
            {
                accessorKey: 'date',
                meta: { title: t('ui.workdays.columns.date') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.workdays.columns.date')}
                    />
                ),
                cell: ({ row }) => row.original.date,
            },
            {
                id: 'status',
                enableSorting: false,
                meta: { title: t('ui.workdays.columns.status') },
                header: () => t('ui.workdays.columns.status'),
                cell: ({ row }) =>
                    row.original.status ? (
                        <Badge
                            variant={
                                STATUS_BADGE[row.original.status_badge ?? ''] ??
                                'outline'
                            }
                        >
                            {row.original.status_label}
                        </Badge>
                    ) : (
                        '—'
                    ),
            },
            {
                accessorKey: 'mark_in_at',
                meta: {
                    title: t('ui.workdays.columns.mark_in'),
                    cellClassName: 'tabular-nums',
                },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.workdays.columns.mark_in')}
                    />
                ),
                cell: ({ row }) => row.original.mark_in_at ?? '—',
            },
            {
                accessorKey: 'mark_out_at',
                meta: {
                    title: t('ui.workdays.columns.mark_out'),
                    cellClassName: 'tabular-nums',
                },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.workdays.columns.mark_out')}
                    />
                ),
                cell: ({ row }) => row.original.mark_out_at ?? '—',
            },
            {
                accessorKey: 'worked_time',
                meta: {
                    title: t('ui.workdays.columns.worked'),
                    cellClassName: 'tabular-nums',
                },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.workdays.columns.worked')}
                    />
                ),
                cell: ({ row }) => row.original.worked_time ?? '—',
            },
            {
                id: 'shift_delta',
                enableSorting: false,
                meta: {
                    title: t('ui.workdays.columns.shift_delta'),
                    cellClassName: 'tabular-nums text-muted-foreground',
                },
                header: () => t('ui.workdays.columns.shift_delta'),
                cell: ({ row }) => {
                    const inDelta = row.original.in_time_difference;
                    const outDelta = row.original.out_time_difference;

                    if (!inDelta && !outDelta) {
                        return '—';
                    }

                    return `${inDelta ?? '—'} / ${outDelta ?? '—'}`;
                },
            },
            {
                id: 'leave_type',
                enableSorting: false,
                meta: { title: t('ui.workdays.columns.leave') },
                header: () => t('ui.workdays.columns.leave'),
                cell: ({ row }) =>
                    row.original.leave_type ? (
                        <Badge variant="outline">
                            {row.original.leave_type}
                        </Badge>
                    ) : (
                        '—'
                    ),
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
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => setViewTarget(row.original)}
                        aria-label={t('ui.workdays.actions.view')}
                    >
                        <Eye className="size-4" />
                    </Button>
                ),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('ui.workdays.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.workdays.title')}
                    description={t('ui.workdays.description')}
                />

                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div className="flex flex-wrap items-center gap-1 rounded-lg bg-muted p-1">
                        {quickRanges.map((range) => {
                            const isActive =
                                from === range.from && to === range.to;

                            return (
                                <button
                                    key={range.key}
                                    type="button"
                                    onClick={() => applyRange(range)}
                                    className={cn(
                                        'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                        isActive
                                            ? 'bg-background text-foreground shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {range.label}
                                </button>
                            );
                        })}
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Input
                            type="date"
                            value={from}
                            onChange={(event) => setFrom(event.target.value)}
                            aria-label={t('ui.workdays.filters.from')}
                            className="w-[150px]"
                        />
                        <span className="text-muted-foreground">–</span>
                        <Input
                            type="date"
                            value={to}
                            onChange={(event) => setTo(event.target.value)}
                            aria-label={t('ui.workdays.filters.to')}
                            className="w-[150px]"
                        />
                    </div>
                </div>

                <DataTable
                    data={workdays}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    extraParams={extraParams}
                    only={['workdays', 'filters']}
                    emptyLabel={t('ui.workdays.empty')}
                    enableRowSelection
                    getRowId={(row) => String(row.id)}
                    renderSelectionActions={(rows, reset) => (
                        <>
                            <span className="text-sm font-medium">
                                {t('ui.workdays.selected', {
                                    count: rows.length,
                                })}
                            </span>
                            <Button
                                size="sm"
                                onClick={() => openBulk(rows, reset)}
                            >
                                {t('ui.workdays.bulk.trigger')}
                            </Button>
                        </>
                    )}
                    toolbar={
                        <div className="flex flex-wrap items-center gap-2">
                            <DataTableFacetedFilter
                                title={t('ui.workdays.filters.status')}
                                options={statusOptions}
                                selected={statuses}
                                onChange={setStatuses}
                            />
                            <DataTableFacetedFilter
                                title={t('ui.workdays.filters.employee')}
                                options={employeeOptions}
                                selected={employees}
                                onChange={setEmployees}
                            />
                            <DataTableFacetedFilter
                                title={t('ui.workdays.filters.position')}
                                options={positionOptions}
                                selected={positions}
                                onChange={setPositions}
                            />
                            <DataTableFacetedFilter
                                title={t('ui.workdays.filters.premise')}
                                options={premiseOptions}
                                selected={premises}
                                onChange={setPremises}
                            />
                        </div>
                    }
                />
            </div>

            <Sheet
                open={viewTarget !== null}
                onOpenChange={(open) => !open && setViewTarget(null)}
            >
                <SheetContent className="overflow-y-auto">
                    <SheetHeader>
                        <SheetTitle>{t('ui.workdays.detail.title')}</SheetTitle>
                        <SheetDescription>
                            {viewTarget?.employee ?? '—'} · {viewTarget?.date}
                        </SheetDescription>
                    </SheetHeader>

                    {viewTarget && (
                        <dl className="grid gap-3 px-4 pb-6">
                            <DetailRow
                                label={t('ui.workdays.columns.status')}
                                value={
                                    viewTarget.status ? (
                                        <Badge
                                            variant={
                                                STATUS_BADGE[
                                                    viewTarget.status_badge ??
                                                        ''
                                                ] ?? 'outline'
                                            }
                                        >
                                            {viewTarget.status_label}
                                        </Badge>
                                    ) : (
                                        '—'
                                    )
                                }
                            />
                            <DetailRow
                                label={t('ui.workdays.columns.shift')}
                                value={viewTarget.shift ?? '—'}
                            />
                            <DetailRow
                                label={t('ui.workdays.columns.mark_in')}
                                value={viewTarget.mark_in_at ?? '—'}
                            />
                            <DetailRow
                                label={t('ui.workdays.columns.mark_out')}
                                value={viewTarget.mark_out_at ?? '—'}
                            />
                            <DetailRow
                                label={t('ui.workdays.columns.worked')}
                                value={viewTarget.worked_time ?? '—'}
                            />
                            <DetailRow
                                label={t('ui.workdays.detail.in_delta')}
                                value={viewTarget.in_time_difference ?? '—'}
                            />
                            <DetailRow
                                label={t('ui.workdays.detail.out_delta')}
                                value={viewTarget.out_time_difference ?? '—'}
                            />
                            <DetailRow
                                label={t('ui.workdays.columns.leave')}
                                value={viewTarget.leave_type ?? '—'}
                            />
                            <DetailRow
                                label={t('ui.workdays.detail.pending')}
                                value={viewTarget.pending_modifications}
                            />
                        </dl>
                    )}
                </SheetContent>
            </Sheet>

            <Dialog
                open={bulkTargets.length > 0}
                onOpenChange={(open) => !open && setBulkTargets([])}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('ui.workdays.bulk.title')}</DialogTitle>
                        <DialogDescription>
                            {t('ui.workdays.bulk.description', {
                                count: bulkTargets.length,
                            })}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-2">
                        <FormField
                            label={t('ui.workdays.bulk.mark_type')}
                            htmlFor="bulk_mark_type"
                            required
                            error={bulkForm.errors.mark_type}
                        >
                            <Select
                                value={bulkForm.data.mark_type}
                                onValueChange={(value) =>
                                    bulkForm.setData('mark_type', value)
                                }
                            >
                                <SelectTrigger id="bulk_mark_type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {markTypeOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField
                            label={t('ui.workdays.bulk.time')}
                            htmlFor="bulk_time"
                            required
                            error={bulkForm.errors.time}
                        >
                            <Input
                                id="bulk_time"
                                type="time"
                                value={bulkForm.data.time}
                                onChange={(event) =>
                                    bulkForm.setData('time', event.target.value)
                                }
                            />
                        </FormField>

                        <FormField
                            label={t('ui.workdays.bulk.reason')}
                            htmlFor="bulk_reason"
                            required
                            error={bulkForm.errors.reason}
                        >
                            <Select
                                value={bulkForm.data.reason}
                                onValueChange={(value) =>
                                    bulkForm.setData('reason', value)
                                }
                            >
                                <SelectTrigger id="bulk_reason">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {reasonOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField
                            label={t('ui.workdays.bulk.notes')}
                            htmlFor="bulk_notes"
                            error={bulkForm.errors.notes}
                        >
                            <textarea
                                id="bulk_notes"
                                rows={3}
                                value={bulkForm.data.notes}
                                onChange={(event) =>
                                    bulkForm.setData(
                                        'notes',
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
                            onClick={() => setBulkTargets([])}
                        >
                            {t('ui.common.cancel')}
                        </Button>
                        <Button
                            onClick={submitBulk}
                            disabled={bulkForm.processing}
                        >
                            {t('ui.workdays.bulk.submit')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
