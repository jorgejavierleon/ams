import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { ArrowLeft, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ComboboxOption } from '@/components/combobox';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import Heading from '@/components/heading';
import OvertimePactFormDialog from '@/components/overtime-pact-form-dialog';
import type { OvertimePactFormTarget } from '@/components/overtime-pact-form-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { index as overtimeIndex } from '@/routes/overtime';
import { activate, index, revoke } from '@/routes/overtime/pacts';
import type { Paginated } from '@/types/ui';

type OvertimePact = {
    id: number;
    user_id: number;
    employee: string | null;
    start_date: string;
    end_date: string;
    status: {
        value: string;
        label: string;
        variant: 'default' | 'secondary' | 'destructive' | 'outline';
    };
};

type Props = {
    pacts: Paginated<OvertimePact>;
    filters: {
        search: string | null;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
    employeeOptions: ComboboxOption[];
};

export default function OvertimePactsIndex({
    pacts,
    filters,
    employeeOptions,
}: Props) {
    const { t } = useTranslations();
    const [formOpen, setFormOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<OvertimePactFormTarget>(null);
    const [revokeTarget, setRevokeTarget] = useState<OvertimePact | null>(null);

    const columns = useMemo<ColumnDef<OvertimePact>[]>(
        () => [
            {
                accessorKey: 'employee',
                meta: { title: t('ui.overtime.pacts.columns.employee') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.overtime.pacts.columns.employee')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="font-medium">{row.original.employee}</span>
                ),
            },
            {
                accessorKey: 'start_date',
                meta: { title: t('ui.overtime.pacts.columns.start_date') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.overtime.pacts.columns.start_date')}
                    />
                ),
                cell: ({ row }) => row.original.start_date,
            },
            {
                accessorKey: 'end_date',
                meta: { title: t('ui.overtime.pacts.columns.end_date') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.overtime.pacts.columns.end_date')}
                    />
                ),
                cell: ({ row }) => row.original.end_date,
            },
            {
                accessorKey: 'status',
                meta: { title: t('ui.overtime.pacts.columns.status') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.overtime.pacts.columns.status')}
                    />
                ),
                cell: ({ row }) => (
                    <Badge variant={row.original.status.variant}>
                        {row.original.status.label}
                    </Badge>
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
                    <div className="flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={() => {
                                setEditTarget({
                                    id: row.original.id,
                                    user_id: row.original.user_id,
                                    start_date: row.original.start_date,
                                    end_date: row.original.end_date,
                                });
                                setFormOpen(true);
                            }}
                            className="text-sm text-primary underline-offset-4 hover:underline"
                        >
                            {t('ui.overtime.pacts.actions.edit')}
                        </button>
                        {row.original.status.value === 'active' && (
                            <button
                                type="button"
                                onClick={() => setRevokeTarget(row.original)}
                                className="text-sm text-destructive underline-offset-4 hover:underline"
                            >
                                {t('ui.overtime.pacts.actions.revoke')}
                            </button>
                        )}
                        {row.original.status.value === 'revoked' && (
                            <button
                                type="button"
                                onClick={() =>
                                    router.patch(
                                        activate(row.original.id).url,
                                        undefined,
                                        { preserveScroll: true },
                                    )
                                }
                                className="text-sm text-primary underline-offset-4 hover:underline"
                            >
                                {t('ui.overtime.pacts.actions.activate')}
                            </button>
                        )}
                    </div>
                ),
            },
        ],
        [t],
    );

    function openCreate() {
        setEditTarget(null);
        setFormOpen(true);
    }

    function confirmRevoke() {
        if (!revokeTarget) {
            return;
        }

        router.patch(revoke(revokeTarget.id).url, undefined, {
            preserveScroll: true,
            onFinish: () => setRevokeTarget(null),
        });
    }

    return (
        <>
            <Head title={t('ui.overtime.pacts.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={overtimeIndex()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div className="flex flex-1 items-center justify-between gap-4">
                        <Heading
                            title={t('ui.overtime.pacts.title')}
                            description={t('ui.overtime.pacts.description')}
                        />
                        <Button onClick={openCreate}>
                            <Plus className="size-4" />
                            {t('ui.overtime.pacts.new')}
                        </Button>
                    </div>
                </div>

                <DataTable
                    data={pacts}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    only={['pacts', 'filters']}
                    searchPlaceholder={t(
                        'ui.overtime.pacts.search_placeholder',
                    )}
                    emptyLabel={t('ui.overtime.pacts.empty')}
                />
            </div>

            <OvertimePactFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                pact={editTarget}
                employeeOptions={employeeOptions}
            />

            <ConfirmDialog
                open={revokeTarget !== null}
                onOpenChange={(open) => !open && setRevokeTarget(null)}
                title={t('ui.overtime.pacts.revoke_dialog.title')}
                description={t('ui.overtime.pacts.revoke_dialog.description', {
                    employee: revokeTarget?.employee ?? '',
                })}
                confirmLabel={t('ui.overtime.pacts.revoke_dialog.confirm')}
                onConfirm={confirmRevoke}
            />
        </>
    );
}
