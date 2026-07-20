import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useMemo, useState } from 'react';
import { Combobox } from '@/components/combobox';
import type { ComboboxOption } from '@/components/combobox';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/hooks/use-translations';
import { index } from '@/routes/saas/audit-log';
import type { Paginated } from '@/types/ui';

type Activity = {
    id: number;
    log_name: string | null;
    event: string | null;
    description: string;
    subject_type: string | null;
    subject_id: number | null;
    causer: { name: string; email: string } | null;
    properties: Record<string, unknown> | null;
    created_at: string;
};

type Option = {
    id: number;
    name: string;
};

type Props = {
    activities: Paginated<Activity>;
    causers: Option[];
    organizations: Option[];
    filters: {
        search: string | null;
        causer_id: number | null;
        organization_id: number | null;
        date_from: string | null;
        date_to: string | null;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
};

const ALL = 'all';

export default function AuditLogIndex({
    activities,
    causers,
    organizations,
    filters,
}: Props) {
    const { t } = useTranslations();
    const [changesTarget, setChangesTarget] = useState<Activity | null>(null);
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [causerId, setCauserId] = useState(
        filters.causer_id ? String(filters.causer_id) : ALL,
    );
    const [organizationId, setOrganizationId] = useState(
        filters.organization_id ? String(filters.organization_id) : ALL,
    );

    const causerOptions = useMemo<ComboboxOption[]>(
        () => [
            { value: ALL, label: t('ui.saas_audit_log.filters.all_causers') },
            ...causers.map((causer) => ({
                value: String(causer.id),
                label: causer.name,
            })),
        ],
        [causers, t],
    );

    const organizationOptions = useMemo<ComboboxOption[]>(
        () => [
            {
                value: ALL,
                label: t('ui.saas_audit_log.filters.all_organizations'),
            },
            ...organizations.map((organization) => ({
                value: String(organization.id),
                label: organization.name,
            })),
        ],
        [organizations, t],
    );

    const extraParams = useMemo(
        () => ({
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
            causer_id: causerId !== ALL ? causerId : undefined,
            organization_id: organizationId !== ALL ? organizationId : undefined,
        }),
        [dateFrom, dateTo, causerId, organizationId],
    );

    const columns = useMemo<ColumnDef<Activity>[]>(
        () => [
            {
                accessorKey: 'created_at',
                meta: { title: t('ui.saas_audit_log.columns.timestamp') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.saas_audit_log.columns.timestamp')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="font-mono text-xs whitespace-nowrap">
                        {row.original.created_at}
                    </span>
                ),
            },
            {
                accessorKey: 'causer',
                enableSorting: false,
                meta: { title: t('ui.saas_audit_log.columns.causer') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.saas_audit_log.columns.causer')}
                    />
                ),
                cell: ({ row }) =>
                    row.original.causer ? (
                        <div className="flex flex-col">
                            <span className="font-medium">
                                {row.original.causer.name}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {row.original.causer.email}
                            </span>
                        </div>
                    ) : (
                        <span className="text-muted-foreground">
                            {t('ui.saas_audit_log.system')}
                        </span>
                    ),
            },
            {
                accessorKey: 'event',
                enableSorting: false,
                meta: { title: t('ui.saas_audit_log.columns.event') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.saas_audit_log.columns.event')}
                    />
                ),
                cell: ({ row }) =>
                    row.original.event ? (
                        <Badge variant="secondary">{row.original.event}</Badge>
                    ) : (
                        <span className="text-muted-foreground">—</span>
                    ),
            },
            {
                accessorKey: 'subject_type',
                enableSorting: false,
                meta: { title: t('ui.saas_audit_log.columns.subject') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.saas_audit_log.columns.subject')}
                    />
                ),
                cell: ({ row }) =>
                    row.original.subject_type ? (
                        <span className="whitespace-nowrap">
                            {row.original.subject_type}
                            {row.original.subject_id !== null && (
                                <span className="text-muted-foreground">
                                    {' '}
                                    #{row.original.subject_id}
                                </span>
                            )}
                        </span>
                    ) : (
                        <span className="text-muted-foreground">—</span>
                    ),
            },
            {
                accessorKey: 'description',
                enableSorting: false,
                meta: { title: t('ui.saas_audit_log.columns.description') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.saas_audit_log.columns.description')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="text-muted-foreground">
                        {row.original.description}
                    </span>
                ),
            },
            {
                id: 'changes',
                enableSorting: false,
                enableHiding: false,
                meta: {
                    title: t('ui.saas_audit_log.columns.changes'),
                    headClassName: 'text-right',
                    cellClassName: 'text-right',
                },
                header: () => null,
                cell: ({ row }) =>
                    row.original.properties ? (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setChangesTarget(row.original)}
                        >
                            {t('ui.saas_audit_log.view_changes')}
                        </Button>
                    ) : (
                        <span className="text-muted-foreground">—</span>
                    ),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('ui.saas_audit_log.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.saas_audit_log.title')}
                    description={t('ui.saas_audit_log.description')}
                />

                <DataTable
                    data={activities}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    extraParams={extraParams}
                    only={['activities', 'filters']}
                    searchPlaceholder={t(
                        'ui.saas_audit_log.filters.search_placeholder',
                    )}
                    emptyLabel={t('ui.saas_audit_log.empty')}
                    toolbar={
                        <div className="flex flex-wrap items-center gap-3">
                            <div className="flex items-center gap-2">
                                <Label
                                    htmlFor="date_from"
                                    className="text-xs text-muted-foreground"
                                >
                                    {t('ui.saas_audit_log.filters.date_from')}
                                </Label>
                                <Input
                                    id="date_from"
                                    type="date"
                                    value={dateFrom}
                                    max={dateTo || undefined}
                                    onChange={(event) =>
                                        setDateFrom(event.target.value)
                                    }
                                    className="w-auto"
                                />
                            </div>
                            <div className="flex items-center gap-2">
                                <Label
                                    htmlFor="date_to"
                                    className="text-xs text-muted-foreground"
                                >
                                    {t('ui.saas_audit_log.filters.date_to')}
                                </Label>
                                <Input
                                    id="date_to"
                                    type="date"
                                    value={dateTo}
                                    min={dateFrom || undefined}
                                    onChange={(event) =>
                                        setDateTo(event.target.value)
                                    }
                                    className="w-auto"
                                />
                            </div>
                            <div className="w-56">
                                <Combobox
                                    options={organizationOptions}
                                    value={organizationId}
                                    onChange={setOrganizationId}
                                    placeholder={t(
                                        'ui.saas_audit_log.filters.organization',
                                    )}
                                    searchPlaceholder={t(
                                        'ui.saas_audit_log.filters.search_organizations',
                                    )}
                                    emptyLabel={t(
                                        'ui.saas_audit_log.filters.no_organizations',
                                    )}
                                />
                            </div>
                            <div className="w-56">
                                <Combobox
                                    options={causerOptions}
                                    value={causerId}
                                    onChange={setCauserId}
                                    placeholder={t(
                                        'ui.saas_audit_log.filters.causer',
                                    )}
                                    searchPlaceholder={t(
                                        'ui.saas_audit_log.filters.search_causers',
                                    )}
                                    emptyLabel={t(
                                        'ui.saas_audit_log.filters.no_causers',
                                    )}
                                />
                            </div>
                        </div>
                    }
                />
            </div>

            <Dialog
                open={changesTarget !== null}
                onOpenChange={(open) => !open && setChangesTarget(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('ui.saas_audit_log.changes_dialog.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('ui.saas_audit_log.changes_dialog.description')}
                        </DialogDescription>
                    </DialogHeader>
                    <pre className="max-h-96 overflow-auto rounded-md bg-muted p-4 text-xs">
                        {changesTarget?.properties
                            ? JSON.stringify(changesTarget.properties, null, 2)
                            : t('ui.saas_audit_log.no_changes')}
                    </pre>
                </DialogContent>
            </Dialog>
        </>
    );
}
