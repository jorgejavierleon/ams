import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { ArrowLeft, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ComboboxOption } from '@/components/combobox';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import Heading from '@/components/heading';
import RestDayBalanceConsumeDialog from '@/components/rest-day-balance-consume-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { index as overtimeIndex } from '@/routes/overtime';
import { index } from '@/routes/overtime/rest-day-balances';
import type { Paginated } from '@/types/ui';

type RestDayBalanceLine = {
    id: number;
    user_id: number;
    employee: string | null;
    accrued_hours: string;
    rest_hours: string;
    consumed_hours: string;
    remaining_hours: string;
    accrual_date: string;
    expiry_date: string;
    status: {
        value: string;
        label: string;
        variant: 'default' | 'secondary' | 'destructive' | 'outline';
    };
    payable_from_expiry: string | null;
};

type Props = {
    balances: Paginated<RestDayBalanceLine>;
    filters: {
        search: string | null;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
    employeeOptions: ComboboxOption[];
};

export default function OvertimeRestDayBalancesIndex({
    balances,
    filters,
    employeeOptions,
}: Props) {
    const { t } = useTranslations();
    const [consumeOpen, setConsumeOpen] = useState(false);

    const columns = useMemo<ColumnDef<RestDayBalanceLine>[]>(
        () => [
            {
                accessorKey: 'employee',
                meta: {
                    title: t('ui.overtime.rest_day_balances.columns.employee'),
                },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t(
                            'ui.overtime.rest_day_balances.columns.employee',
                        )}
                    />
                ),
                cell: ({ row }) => (
                    <span className="font-medium">{row.original.employee}</span>
                ),
            },
            {
                accessorKey: 'accrued_hours',
                meta: {
                    title: t(
                        'ui.overtime.rest_day_balances.columns.accrued_hours',
                    ),
                },
                header: () =>
                    t('ui.overtime.rest_day_balances.columns.accrued_hours'),
                cell: ({ row }) => row.original.accrued_hours,
            },
            {
                accessorKey: 'rest_hours',
                meta: {
                    title: t(
                        'ui.overtime.rest_day_balances.columns.rest_hours',
                    ),
                },
                header: () =>
                    t('ui.overtime.rest_day_balances.columns.rest_hours'),
                cell: ({ row }) => row.original.rest_hours,
            },
            {
                accessorKey: 'consumed_hours',
                meta: {
                    title: t(
                        'ui.overtime.rest_day_balances.columns.consumed_hours',
                    ),
                },
                header: () =>
                    t('ui.overtime.rest_day_balances.columns.consumed_hours'),
                cell: ({ row }) => row.original.consumed_hours,
            },
            {
                accessorKey: 'remaining_hours',
                meta: {
                    title: t(
                        'ui.overtime.rest_day_balances.columns.remaining_hours',
                    ),
                },
                header: () =>
                    t('ui.overtime.rest_day_balances.columns.remaining_hours'),
                cell: ({ row }) => (
                    <span className="font-medium">
                        {row.original.remaining_hours}
                    </span>
                ),
            },
            {
                accessorKey: 'expiry_date',
                meta: {
                    title: t(
                        'ui.overtime.rest_day_balances.columns.expiry_date',
                    ),
                },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t(
                            'ui.overtime.rest_day_balances.columns.expiry_date',
                        )}
                    />
                ),
                cell: ({ row }) => row.original.expiry_date,
            },
            {
                accessorKey: 'status',
                meta: {
                    title: t('ui.overtime.rest_day_balances.columns.status'),
                },
                header: () => t('ui.overtime.rest_day_balances.columns.status'),
                cell: ({ row }) => (
                    <div className="flex flex-col gap-1">
                        <Badge variant={row.original.status.variant}>
                            {row.original.status.label}
                        </Badge>
                        {row.original.payable_from_expiry && (
                            <span
                                className="text-xs text-muted-foreground"
                                title={t(
                                    'ui.overtime.rest_day_balances.expired_hint',
                                )}
                            >
                                {row.original.payable_from_expiry}
                            </span>
                        )}
                    </div>
                ),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('ui.overtime.rest_day_balances.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={overtimeIndex()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div className="flex flex-1 items-center justify-between gap-4">
                        <Heading
                            title={t('ui.overtime.rest_day_balances.title')}
                            description={t(
                                'ui.overtime.rest_day_balances.description',
                            )}
                        />
                        <Button onClick={() => setConsumeOpen(true)}>
                            <Plus className="size-4" />
                            {t(
                                'ui.overtime.rest_day_balances.register_consumption',
                            )}
                        </Button>
                    </div>
                </div>

                <DataTable
                    data={balances}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    only={['balances', 'filters']}
                    searchPlaceholder={t(
                        'ui.overtime.rest_day_balances.search_placeholder',
                    )}
                    emptyLabel={t('ui.overtime.rest_day_balances.empty')}
                />
            </div>

            <RestDayBalanceConsumeDialog
                open={consumeOpen}
                onOpenChange={setConsumeOpen}
                employeeOptions={employeeOptions}
            />
        </>
    );
}
