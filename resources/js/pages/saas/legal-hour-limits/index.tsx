import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { CalendarClock, Pencil, Plus } from 'lucide-react';
import { useCallback, useMemo } from 'react';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTranslations } from '@/hooks/use-translations';
import { correct, create, index } from '@/routes/saas/legal-hour-limits';

type VersionStatus = 'in_force' | 'scheduled' | 'superseded';

type LegalHourLimitVersion = {
    id: number;
    effective_from: string;
    effective_until: string | null;
    status: VersionStatus;
    ordinary_weekly_hours: number;
    ordinary_daily_hours: number;
    max_overtime_daily_hours: number;
    max_overtime_weekly_hours: number;
    max_total_daily_hours: number;
    max_total_weekly_hours: number;
    legal_reference: string;
    notes: string | null;
    calculated_days: number;
};

type Props = {
    versions: { data: LegalHourLimitVersion[] };
    filters: {
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
    today: string;
};

const STATUS_VARIANT: Record<
    VersionStatus,
    'default' | 'secondary' | 'outline'
> = {
    in_force: 'default',
    scheduled: 'outline',
    superseded: 'secondary',
};

export default function LegalHourLimitsIndex({ versions, filters }: Props) {
    const { t, formatDate, formatNumber } = useTranslations();

    const rows = versions.data;
    const inForce = rows.find((version) => version.status === 'in_force');
    const scheduled = rows.filter((version) => version.status === 'scheduled');

    // Stable identities so the column definitions below can depend on them.
    const day = useCallback(
        (date: string) => formatDate(`${date}T00:00:00`),
        [formatDate],
    );
    const hours = useCallback(
        (value: number) => formatNumber(value),
        [formatNumber],
    );

    const columns = useMemo<ColumnDef<LegalHourLimitVersion>[]>(
        () => [
            {
                accessorKey: 'effective_from',
                meta: { title: t('ui.saas_legal_hour_limits.columns.period') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.saas_legal_hour_limits.columns.period')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="font-medium whitespace-nowrap">
                        {row.original.effective_until
                            ? t('ui.saas_legal_hour_limits.range', {
                                  from: day(row.original.effective_from),
                                  to: day(row.original.effective_until),
                              })
                            : t('ui.saas_legal_hour_limits.from', {
                                  date: day(row.original.effective_from),
                              })}
                    </span>
                ),
            },
            {
                accessorKey: 'status',
                enableSorting: false,
                meta: { title: t('ui.saas_legal_hour_limits.columns.status') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.saas_legal_hour_limits.columns.status')}
                    />
                ),
                cell: ({ row }) => (
                    <Badge variant={STATUS_VARIANT[row.original.status]}>
                        {t(
                            `ui.saas_legal_hour_limits.status.${row.original.status}`,
                        )}
                    </Badge>
                ),
            },
            {
                accessorKey: 'ordinary_weekly_hours',
                meta: {
                    title: t(
                        'ui.saas_legal_hour_limits.columns.ordinary_weekly_hours',
                    ),
                },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t(
                            'ui.saas_legal_hour_limits.columns.ordinary_weekly_hours',
                        )}
                    />
                ),
                cell: ({ row }) => (
                    <span className="font-medium">
                        {t('ui.saas_legal_hour_limits.hours', {
                            hours: hours(row.original.ordinary_weekly_hours),
                        })}
                    </span>
                ),
            },
            {
                accessorKey: 'ordinary_daily_hours',
                enableSorting: false,
                meta: {
                    title: t(
                        'ui.saas_legal_hour_limits.columns.ordinary_daily_hours',
                    ),
                },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t(
                            'ui.saas_legal_hour_limits.columns.ordinary_daily_hours',
                        )}
                    />
                ),
                cell: ({ row }) =>
                    t('ui.saas_legal_hour_limits.hours', {
                        hours: hours(row.original.ordinary_daily_hours),
                    }),
            },
            {
                id: 'overtime',
                enableSorting: false,
                meta: {
                    title: t('ui.saas_legal_hour_limits.columns.overtime'),
                },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.saas_legal_hour_limits.columns.overtime')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="whitespace-nowrap text-muted-foreground">
                        {t('ui.saas_legal_hour_limits.hours_per_day', {
                            hours: hours(row.original.max_overtime_daily_hours),
                        })}
                        {' · '}
                        {t('ui.saas_legal_hour_limits.hours_per_week', {
                            hours: hours(
                                row.original.max_overtime_weekly_hours,
                            ),
                        })}
                    </span>
                ),
            },
            {
                id: 'total',
                enableSorting: false,
                meta: { title: t('ui.saas_legal_hour_limits.columns.total') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.saas_legal_hour_limits.columns.total')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="whitespace-nowrap text-muted-foreground">
                        {t('ui.saas_legal_hour_limits.hours_per_day', {
                            hours: hours(row.original.max_total_daily_hours),
                        })}
                        {' · '}
                        {t('ui.saas_legal_hour_limits.hours_per_week', {
                            hours: hours(row.original.max_total_weekly_hours),
                        })}
                    </span>
                ),
            },
            {
                accessorKey: 'legal_reference',
                meta: {
                    title: t(
                        'ui.saas_legal_hour_limits.columns.legal_reference',
                    ),
                },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t(
                            'ui.saas_legal_hour_limits.columns.legal_reference',
                        )}
                    />
                ),
                cell: ({ row }) => row.original.legal_reference,
            },
            {
                accessorKey: 'calculated_days',
                enableSorting: false,
                meta: {
                    title: t(
                        'ui.saas_legal_hour_limits.columns.calculated_days',
                    ),
                },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t(
                            'ui.saas_legal_hour_limits.columns.calculated_days',
                        )}
                    />
                ),
                cell: ({ row }) => formatNumber(row.original.calculated_days),
            },
            {
                id: 'actions',
                enableSorting: false,
                enableHiding: false,
                meta: { title: t('ui.saas_legal_hour_limits.columns.actions') },
                header: () => (
                    <span className="sr-only">
                        {t('ui.saas_legal_hour_limits.columns.actions')}
                    </span>
                ),
                // Never an edit link: a recorded version is only ever changed
                // through the correction flow, which recalculates the days it
                // was applied to and records who changed it and why.
                cell: ({ row }) => (
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={correct(row.original.id)}>
                            <Pencil className="size-4" />
                            {t('ui.saas_legal_hour_limits.correct.action')}
                        </Link>
                    </Button>
                ),
            },
        ],
        [t, day, hours, formatNumber],
    );

    return (
        <>
            <Head title={t('ui.saas_legal_hour_limits.title')} />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <Heading
                        title={t('ui.saas_legal_hour_limits.title')}
                        description={t('ui.saas_legal_hour_limits.description')}
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="size-4" />
                            {t('ui.saas_legal_hour_limits.create.nav')}
                        </Link>
                    </Button>
                </div>

                {inForce && (
                    <Card>
                        <CardHeader>
                            <CardDescription>
                                {t('ui.saas_legal_hour_limits.current.title')}
                            </CardDescription>
                            <CardTitle className="text-3xl">
                                {t('ui.saas_legal_hour_limits.hours_per_week', {
                                    hours: hours(inForce.ordinary_weekly_hours),
                                })}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm text-muted-foreground">
                            <p>
                                {t('ui.saas_legal_hour_limits.current.since', {
                                    date: day(inForce.effective_from),
                                })}
                                {' · '}
                                {inForce.effective_until
                                    ? t(
                                          'ui.saas_legal_hour_limits.current.until',
                                          {
                                              date: day(
                                                  inForce.effective_until,
                                              ),
                                          },
                                      )
                                    : t(
                                          'ui.saas_legal_hour_limits.current.indefinite',
                                      )}
                                {' · '}
                                {inForce.legal_reference}
                            </p>
                        </CardContent>
                    </Card>
                )}

                {scheduled.length > 0 && (
                    <Alert>
                        <CalendarClock />
                        <AlertTitle>
                            {t(
                                'ui.saas_legal_hour_limits.scheduled_notice.title',
                            )}
                        </AlertTitle>
                        <AlertDescription>
                            {t(
                                'ui.saas_legal_hour_limits.scheduled_notice.body',
                                { count: scheduled.length },
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <DataTable
                    data={versions}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    only={['versions', 'filters']}
                    emptyLabel={t('ui.saas_legal_hour_limits.empty')}
                    showPagination={false}
                />
            </div>
        </>
    );
}
